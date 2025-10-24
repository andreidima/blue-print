<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class MigrationController extends Controller
{
    /**
     * Display migration overview and pending actions.
     */
    public function index(): View
    {
        $migrator = $this->bootstrapMigrator();

        $migrationPaths = array_merge([database_path('migrations')], $migrator->paths());
        $migrationFiles = $migrator->getMigrationFiles($migrationPaths);

        $repositoryExists = $migrator->repositoryExists();
        $ranMigrations = collect();
        $ranMigrationNames = collect();

        if ($repositoryExists && Schema::hasTable('migrations')) {
            $ranMigrations = DB::table('migrations')
                ->select(['migration', 'batch'])
                ->orderBy('batch')
                ->orderBy('migration')
                ->get();
            $ranMigrationNames = $ranMigrations->pluck('migration')->flip();
        }

        $pendingMigrations = collect($migrationFiles)
            ->reject(function ($path, $migration) use ($ranMigrationNames) {
                return $ranMigrationNames->has($migration);
            })
            ->map(function ($path, $migration) {
                return [
                    'name' => $migration,
                    'headline' => Str::of($migration)->after('_')->headline(),
                    'path' => $path,
                ];
            })
            ->values();

        $pretendByMigration = [];
        $pretendError = null;

        if ($pendingMigrations->isNotEmpty()) {
            try {
                Artisan::call('migrate', ['--pretend' => true]);
                $pretendOutput = collect(explode(PHP_EOL, Artisan::output()))
                    ->map(fn ($line) => trim($line))
                    ->filter()
                    ->values();

                $currentMigration = null;
                foreach ($pretendOutput as $line) {
                    if (Str::startsWith($line, 'Migrating:')) {
                        $currentMigration = Str::after($line, 'Migrating: ');
                        $pretendByMigration[$currentMigration] = [];
                        continue;
                    }

                    if (Str::startsWith($line, 'Migrated:')) {
                        $currentMigration = null;
                        continue;
                    }

                    if ($currentMigration !== null) {
                        $pretendByMigration[$currentMigration][] = $line;
                    }
                }
            } catch (Throwable $exception) {
                $pretendError = $exception->getMessage();
            }
        }

        $lastBatch = $ranMigrations->max('batch');

        return view('tech.migrations.index', [
            'ranMigrations' => $ranMigrations,
            'pendingMigrations' => $pendingMigrations,
            'pretendByMigration' => $pretendByMigration,
            'pretendError' => $pretendError,
            'lastBatch' => $lastBatch,
            'nextBatch' => ($lastBatch ?? 0) + 1,
            'totals' => [
                'total' => count($migrationFiles),
                'ran' => $ranMigrations->count(),
                'pending' => $pendingMigrations->count(),
            ],
        ]);
    }

    /**
     * Run the outstanding migrations.
     */
    public function run(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_run' => ['accepted'],
        ], [
            'confirm_run.accepted' => 'Confirmă că ai înțeles riscurile pentru a continua.',
        ]);

        try {
            $this->bootstrapMigrator();
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return redirect()
                ->route('tech.migrations.index')
                ->with('migrationStatus', [
                    'type' => 'success',
                    'message' => 'Migrațiile au fost executate.',
                    'output' => $output,
                ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('tech.migrations.index')
                ->with('migrationStatus', [
                    'type' => 'danger',
                    'message' => 'A apărut o eroare la rularea migrațiilor: ' . $exception->getMessage(),
                ]);
        }
    }

    /**
     * Roll back a specific migration file.
     */
    public function undo(Request $request, string $migration): RedirectResponse
    {
        try {
            $migrator = $this->bootstrapMigrator();

            if (!Schema::hasTable('migrations')) {
                return redirect()
                    ->route('tech.migrations.index')
                    ->with('migrationStatus', [
                        'type' => 'warning',
                        'message' => 'Nu există tabelul "migrations". Nicio migrație nu poate fi anulată.',
                    ]);
            }

            $migrationPaths = array_merge([database_path('migrations')], $migrator->paths());
            $migrationFiles = $migrator->getMigrationFiles($migrationPaths);

            if (!isset($migrationFiles[$migration])) {
                return redirect()
                    ->route('tech.migrations.index')
                    ->with('migrationStatus', [
                        'type' => 'danger',
                        'message' => 'Fișierul pentru migrația selectată nu a fost găsit.',
                    ]);
            }

            $existsInRepository = DB::table('migrations')->where('migration', $migration)->exists();

            if (! $existsInRepository) {
                return redirect()
                    ->route('tech.migrations.index')
                    ->with('migrationStatus', [
                        'type' => 'warning',
                        'message' => 'Migrația selectată nu apare ca fiind rulată deja.',
                    ]);
            }

            $absolutePath = $migrationFiles[$migration];
            $relativePath = ltrim(str_replace(base_path(), '', $absolutePath), DIRECTORY_SEPARATOR);
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($relativePath === '') {
                $relativePath = $absolutePath;
            }

            $exitCode = Artisan::call('migrate:rollback', [
                '--path' => $relativePath,
                '--step' => 1,
                '--force' => true,
            ]);

            $output = Artisan::output();

            if ($exitCode !== 0) {
                return redirect()
                    ->route('tech.migrations.index')
                    ->with('migrationStatus', [
                        'type' => 'danger',
                        'message' => 'Comanda de rollback a returnat o eroare. Verifică jurnalul pentru detalii.',
                        'output' => $output,
                    ]);
            }

            return redirect()
                ->route('tech.migrations.index')
                ->with('migrationStatus', [
                    'type' => 'success',
                    'message' => 'Migrația a fost rulată înapoi cu succes.',
                    'output' => $output,
                ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('tech.migrations.index')
                ->with('migrationStatus', [
                    'type' => 'danger',
                    'message' => 'Anularea migrației a eșuat: ' . $exception->getMessage(),
                ]);
        }
    }

    private function bootstrapMigrator(): Migrator
    {
        $migrationConfig = config('database.migrations');
        $migrationTable = 'migrations';
        $migrationConnection = null;

        if (is_string($migrationConfig) && $migrationConfig !== '') {
            $migrationTable = $migrationConfig;
        }

        if (is_array($migrationConfig)) {
            $migrationTable = $migrationConfig['table'] ?? $migrationTable;
            $migrationConnection = $migrationConfig['connection'] ?? null;
        }

        $repository = new DatabaseMigrationRepository(app('db'), $migrationTable);

        if ($migrationConnection !== null) {
            $repository->setSource($migrationConnection);
        }

        app()->instance(MigrationRepositoryInterface::class, $repository);
        app()->instance('migration.repository', $repository);

        $migrator = new Migrator(
            $repository,
            app('db'),
            app(Filesystem::class),
            app(Dispatcher::class)
        );

        app()->instance(Migrator::class, $migrator);
        app()->instance('migrator', $migrator);

        if (method_exists($migrator, 'setContainer')) {
            $migrator->setContainer(app());
        } elseif (method_exists($migrator, 'setApplication')) {
            $migrator->setApplication(app());
        }

        return $migrator;
    }
}
