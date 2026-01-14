<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ProdusController;
use App\Http\Controllers\Tech\CronJobLogController;
use App\Http\Controllers\Tech\ImpersonationController;
use App\Http\Controllers\Tech\MigrationController;


Auth::routes(['register' => false, 'password.request' => false, 'reset' => false]);

Route::redirect('/', '/acasa');

Route::middleware(['auth', 'checkUserActiv'])->group(function () {
    Route::get('/acasa', [HomeController::class, 'index'])->name('acasa');

    Route::resource('/utilizatori', UserController::class)->parameters(['utilizatori' => 'user'])->names('users')
        ->middleware('checkUserRole:Admin,SuperAdmin');

    Route::resource('/clienti', ClientController::class)->parameters(['clienti' => 'client'])->names('clienti');
    Route::resource('/produse', ProdusController::class)->parameters(['produse' => 'produs'])->names('produse');
    Route::resource('/comenzi', ComandaController::class)->parameters(['comenzi' => 'comanda'])->names('comenzi');
    Route::get('/cereri-oferta', [ComandaController::class, 'cereriOferta'])->name('cereri-oferta');
    Route::post('/comenzi/{comanda}/produse', [ComandaController::class, 'storeProdus'])->name('comenzi.produse.store');
    Route::post('/comenzi/{comanda}/atasamente', [ComandaController::class, 'storeAtasament'])->name('comenzi.atasamente.store');
    Route::post('/comenzi/{comanda}/mockupuri', [ComandaController::class, 'storeMockup'])->name('comenzi.mockupuri.store');
    Route::post('/comenzi/{comanda}/plati', [ComandaController::class, 'storePlata'])->name('comenzi.plati.store');
    Route::post('/comenzi/{comanda}/trimite-sms', [ComandaController::class, 'trimiteSms'])->name('comenzi.trimite-sms');
    Route::post('/comenzi/{comanda}/trimite-email', [ComandaController::class, 'trimiteEmail'])->name('comenzi.trimite-email');

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
});
