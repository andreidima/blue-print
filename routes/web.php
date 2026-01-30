<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ComandaSmsController;
use App\Http\Controllers\ProdusController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\WooCommerceWebhookController;
use App\Http\Controllers\Tech\CronJobLogController;
use App\Http\Controllers\Tech\ImpersonationController;
use App\Http\Controllers\Tech\MigrationController;


Auth::routes(['register' => false, 'password.request' => false, 'reset' => false]);

Route::redirect('/', '/acasa');

Route::post('/webhooks/woocommerce/orders', [WooCommerceWebhookController::class, 'handle'])
    ->name('webhooks.woocommerce.orders')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::middleware(['auth', 'checkUserActiv'])->group(function () {
    Route::get('/acasa', [HomeController::class, 'index'])->name('acasa');

    Route::resource('/utilizatori', UserController::class)->parameters(['utilizatori' => 'user'])->names('users')
        ->middleware('checkUserRole:Supervizor,SuperAdmin');

    Route::get('/clienti/select-options', [ClientController::class, 'selectOptions'])->name('clienti.select-options');
    Route::post('/clienti/quick-store', [ClientController::class, 'quickStore'])->name('clienti.quick-store');
    Route::resource('/clienti', ClientController::class)->parameters(['clienti' => 'client'])->names('clienti');
    Route::get('/produse/select-options', [ProdusController::class, 'selectOptions'])->name('produse.select-options');
    Route::post('/produse/quick-store', [ProdusController::class, 'quickStore'])->name('produse.quick-store');
    Route::resource('/produse', ProdusController::class)->parameters(['produse' => 'produs'])->names('produse');
    Route::resource('/comenzi', ComandaController::class)->parameters(['comenzi' => 'comanda'])->names('comenzi');
    Route::get('/cereri-oferta', [ComandaController::class, 'cereriOferta'])->name('cereri-oferta');
    Route::post('/comenzi/{comanda}/produse', [ComandaController::class, 'storeProdus'])->name('comenzi.produse.store');
    Route::delete('/comenzi/{comanda}/produse/{linie}', [ComandaController::class, 'destroyProdus'])->name('comenzi.produse.destroy');
    Route::post('/comenzi/{comanda}/atasamente', [ComandaController::class, 'storeAtasament'])->name('comenzi.atasamente.store');
    Route::get('/comenzi/{comanda}/atasamente/{atasament}', [ComandaController::class, 'viewAtasament'])->name('comenzi.atasamente.view');
    Route::get('/comenzi/{comanda}/atasamente/{atasament}/download', [ComandaController::class, 'downloadAtasament'])->name('comenzi.atasamente.download');
    Route::delete('/comenzi/{comanda}/atasamente/{atasament}', [ComandaController::class, 'destroyAtasament'])->name('comenzi.atasamente.destroy');
    Route::post('/comenzi/{comanda}/facturi', [ComandaController::class, 'storeFactura'])->name('comenzi.facturi.store');
    Route::get('/comenzi/{comanda}/facturi/{factura}', [ComandaController::class, 'viewFactura'])->name('comenzi.facturi.view');
    Route::get('/comenzi/{comanda}/facturi/{factura}/download', [ComandaController::class, 'downloadFactura'])->name('comenzi.facturi.download');
    Route::delete('/comenzi/{comanda}/facturi/{factura}', [ComandaController::class, 'destroyFactura'])->name('comenzi.facturi.destroy');
    Route::post('/comenzi/{comanda}/facturi/trimite-email', [ComandaController::class, 'trimiteFacturaEmail'])->name('comenzi.facturi.trimite-email');
    Route::post('/comenzi/{comanda}/mockupuri', [ComandaController::class, 'storeMockup'])->name('comenzi.mockupuri.store');
    Route::get('/comenzi/{comanda}/mockupuri/{mockup}', [ComandaController::class, 'viewMockup'])->name('comenzi.mockupuri.view');
    Route::get('/comenzi/{comanda}/mockupuri/{mockup}/download', [ComandaController::class, 'downloadMockup'])->name('comenzi.mockupuri.download');
    Route::delete('/comenzi/{comanda}/mockupuri/{mockup}', [ComandaController::class, 'destroyMockup'])->name('comenzi.mockupuri.destroy');
    Route::get('/comenzi/{comanda}/pdf/oferta', [ComandaController::class, 'downloadOfertaPdf'])->name('comenzi.pdf.oferta');
    Route::get('/comenzi/{comanda}/pdf/fisa-interna', [ComandaController::class, 'downloadFisaInternaPdf'])->name('comenzi.pdf.fisa-interna');
    Route::post('/comenzi/{comanda}/pdf/oferta/trimite-email', [ComandaController::class, 'trimiteOfertaEmail'])->name('comenzi.pdf.oferta.trimite-email');
    Route::post('/comenzi/{comanda}/plati', [ComandaController::class, 'storePlata'])->name('comenzi.plati.store');
    Route::delete('/comenzi/{comanda}/plati/{plata}', [ComandaController::class, 'destroyPlata'])->name('comenzi.plati.destroy');
    Route::post('/comenzi/{comanda}/aproba-cerere', [ComandaController::class, 'approveAssignments'])->name('comenzi.aproba-cerere');
    Route::get('/comenzi/{comanda}/sms', [ComandaSmsController::class, 'show'])->name('comenzi.sms.show');
    Route::post('/comenzi/{comanda}/sms', [ComandaSmsController::class, 'send'])->name('comenzi.sms.send');
    Route::post('/comenzi/{comanda}/trimite-sms', [ComandaController::class, 'trimiteSms'])->name('comenzi.trimite-sms');
    Route::post('/comenzi/{comanda}/trimite-email', [ComandaController::class, 'trimiteEmail'])->name('comenzi.trimite-email');

    Route::resource('/sms-templates', SmsTemplateController::class)
        ->except(['show']);

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
