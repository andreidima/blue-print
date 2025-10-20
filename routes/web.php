<?php

use Illuminate\Support\Facades\Route;

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
        return response(PHP_BINARY . PHP_EOL, 200)
            ->header('Content-Type', 'text/plain');
    })->name('diagnostics.php-binary');

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
