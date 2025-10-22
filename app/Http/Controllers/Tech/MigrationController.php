<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use Illuminate\Database\MigrationServiceProvider;
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
        app()->register(MigrationServiceProvider::class);

        /** @var \Illuminate\Database\Migrations\Migrator $migrator */
        $migrator = app('migrator');

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
            app()->register(MigrationServiceProvider::class);
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
            app()->register(MigrationServiceProvider::class);
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
}
