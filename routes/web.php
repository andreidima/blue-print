<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

use App\Http\Controllers\AcasaController;
use App\Http\Controllers\ProdusController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InventarController;
use App\Http\Controllers\ComenziIesiriController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\WooCommerce\OrderController;
use App\Http\Controllers\Tech\CronJobLogController;
use App\Http\Controllers\Tech\ImpersonationController;
use App\Http\Controllers\Tech\MigrationController;


Auth::routes(['register' => false, 'password.request' => false, 'reset' => false]);

Route::redirect('/', '/acasa');

// Route::get('verificare/comenzi-site', [OrderController::class, 'publicIndex'])
//     ->name('woocommerce.orders.preview');

Route::middleware(['auth', 'checkUserActiv'])->group(function () {
    Route::get('/acasa', [AcasaController::class, 'acasa'])->name('acasa');

    Route::resource('produse', ProdusController::class)->parameters(['produse' => 'produs']);
    Route::resource('categorii', CategorieController::class)->parameters(['categorii' => 'categorie']);

    Route::resource('/utilizatori', UserController::class)->parameters(['utilizatori' => 'user'])->names('users')
        ->middleware('checkUserRole:Admin,SuperAdmin');

    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchase_order']);
        Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])
            ->name('purchase-orders.receive');
    });

    Route::prefix('tech')->name('tech.')->middleware('checkUserRole:SuperAdmin')->group(function () {
        Route::get('impersonare', [ImpersonationController::class, 'index'])->name('impersonation.index');
        Route::post('impersonare/{user}', [ImpersonationController::class, 'impersonate'])->name('impersonation.start');

        Route::get('cronjobs', [CronJobLogController::class, 'index'])->name('cronjobs.index');

        Route::get('migratii', [MigrationController::class, 'index'])->name('migrations.index');
        Route::post('migratii/ruleaza', [MigrationController::class, 'run'])->name('migrations.run');
        Route::post('migratii/{migration}/anuleaza', [MigrationController::class, 'undo'])->name('migrations.undo');
    });

    Route::post('impersonare/opreste', [ImpersonationController::class, 'stop'])
        ->name('tech.impersonation.stop');


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


    // 1. Listare comenzi de ieșiri (paginated, cu căutare după nr. comandă)
    Route::get('comenzi-iesiri', [ComenziIesiriController::class, 'index'])
         ->name('comenzi.iesiri.index');

    // 2. Vizualizare detaliu comandă (toate ieșirile pentru un nr. de comandă)
    Route::get('comenzi-iesiri/{nr_comanda}', [ComenziIesiriController::class, 'show'])
         ->name('comenzi.iesiri.show');

    // 3. Generare/descărcare PDF pentru o comandă
    Route::get('comenzi-iesiri/{nr_comanda}/pdf', [ComenziIesiriController::class, 'pdf'])
         ->name('comenzi.iesiri.pdf');

    Route::get('comenzi', [OrderController::class, 'index'])
         ->name('woocommerce.orders.index');

    Route::post('comenzi/sincronizare', [OrderController::class, 'sync'])
         ->name('woocommerce.orders.sync');

    Route::patch('comenzi/{order}/status', [OrderController::class, 'changeStatus'])
         ->name('woocommerce.orders.status-change');
});
