<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

use App\Http\Controllers\AcasaController;
use App\Http\Controllers\ProdusController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InventarController;
use App\Http\Controllers\ComenziIesiriController;


Auth::routes(['register' => false, 'password.request' => false, 'reset' => false]);

Route::redirect('/', '/acasa');

Route::middleware(['auth', 'checkUserActiv'])->group(function () {
    Route::get('/acasa', [AcasaController::class, 'acasa'])->name('acasa');

    Route::resource('produse', ProdusController::class)->parameters(['produse' => 'produs']);
    Route::resource('categorii', CategorieController::class)->parameters(['categorii' => 'categorie']);

    Route::resource('/utilizatori', UserController::class)->parameters(['utilizatori' => 'user'])->names('users')
        ->middleware('checkUserRole:Admin,SuperAdmin');


    // 1️⃣ Print-friendly QR label for a single product
    //    GET /produse/{produs}/eticheta
    Route::get(
        'produse/{produs}/eticheta',
        [ProdusController::class, 'eticheta']
    )->name('produse.eticheta');

    // 2️⃣ Inventory adjustment UI (scan target)
    //    GET  /inventar/{produs}   →
    //    POST /inventar/{produs}   → apply the change
    Route::get(
        'inventar/{produs}',
        [InventarController::class, 'show']
    )->name('inventar.show');
    Route::post(
        'inventar/{produs}',
        [InventarController::class, 'update']
    )->name('inventar.update');



    // Movements listing (intrări / ieșiri)
    Route::get('miscari/intrari', [InventarController::class, 'index'])
         ->name('miscari.intrari')
         ->defaults('tip', 'intrari');

    Route::get('miscari/iesiri', [InventarController::class, 'index'])
         ->name('miscari.iesiri')
         ->defaults('tip', 'iesiri');

    // Undo a movement
    Route::post('miscari/{miscare}/anuleaza', [InventarController::class, 'undo'])
         ->name('miscari.anuleaza');


    // Temporary diagnostic route to reveal the PHP binary used by the web runtime.
    Route::get('diagnostics/php-binary', function () {
        $details = sprintf(
            "binary: %s\nversion: %s\nsapi: %s\n",
            PHP_BINARY,
            PHP_VERSION,
            PHP_SAPI
        );

        return response($details, 200)->header('Content-Type', 'text/plain');
    })->name('diagnostics.php-binary');

    Route::get('diagnostics/php-cli-candidates', function () {
        $candidatePatterns = [
            '/usr/local/bin/ea-php*',
            '/opt/cpanel/ea-php*/root/usr/bin/php',
            '/usr/local/bin/php*',
            '/usr/bin/php*',
        ];

        $candidates = ['/usr/local/bin/lsphp' => true];

        foreach ($candidatePatterns as $pattern) {
            foreach (glob($pattern) ?: [] as $match) {
                $candidates[$match] = true;
            }
        }

        ksort($candidates);

        $results = [];

        $diagnosticScript = "echo json_encode([
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'binary' => PHP_BINARY,
        ]);";

        foreach (array_keys($candidates) as $binary) {
            if (! is_file($binary)) {
                continue;
            }

            $entry = [
                'binary' => $binary,
                'is_executable' => is_executable($binary),
            ];

            if (! $entry['is_executable']) {
                $results[] = $entry + ['status' => 'not executable'];
                continue;
            }

            try {
                $process = new Process([$binary, '-r', $diagnosticScript]);
                $process->setTimeout(5);
                $process->run();

                $entry['status'] = $process->isSuccessful() ? 'ok' : 'error';
                $rawOutput = trim($process->getOutput() ?: $process->getErrorOutput());

                $decoded = json_decode($rawOutput, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $entry['version'] = $decoded['version'] ?? null;
                    $entry['sapi'] = $decoded['sapi'] ?? null;
                    $entry['reported_binary'] = $decoded['binary'] ?? null;
                } else {
                    $entry['output'] = $rawOutput;
                }
            } catch (\Throwable $exception) {
                $entry['status'] = 'exception';
                $entry['output'] = $exception->getMessage();
            }

            $results[] = $entry;
        }

        return response()->json([
            'web_runtime' => [
                'binary' => PHP_BINARY,
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
            ],
            'candidates' => $results,
        ]);
    })->name('diagnostics.php-cli-candidates');

    // 1. Listare comenzi de ieșiri (paginated, cu căutare după nr. comandă)
    Route::get('comenzi-iesiri', [ComenziIesiriController::class, 'index'])
         ->name('comenzi.iesiri.index');

    // 2. Vizualizare detaliu comandă (toate ieșirile pentru un nr. de comandă)
    Route::get('comenzi-iesiri/{nr_comanda}', [ComenziIesiriController::class, 'show'])
         ->name('comenzi.iesiri.show');

    // 3. Generare/descărcare PDF pentru o comandă
    Route::get('comenzi-iesiri/{nr_comanda}/pdf', [ComenziIesiriController::class, 'pdf'])
         ->name('comenzi.iesiri.pdf');
});
