<?php

use Illuminate\Support\Facades\Route;

// La raíz lleva al panel de empresas (pide inicio de sesión si hace falta).
Route::redirect('/', '/app');

// Verificación pública del FUEC (código QR del documento).
Route::get('/verificar/fuec/{codigo}', \App\Http\Controllers\VerificarFuecController::class)
    ->where('codigo', '[a-z0-9]{24}')
    ->middleware('throttle:30,1')
    ->name('fuec.verificar');

// Firma electrónica del contrato (enlace enviado por SMS o correo) y su verificación pública.
// Límites por IP con claves separadas (ver AppServiceProvider): leer, pedir código, firmar y verificar.
Route::middleware('throttle:firma')->where(['token' => '[A-Za-z0-9]{22}', 'codigo' => '[a-z0-9]{24}'])->group(function () {
    Route::get('/f/{token}', [\App\Http\Controllers\FirmaContratoController::class, 'mostrar'])->name('contrato.firma');
    Route::get('/f/{token}/pdf', [\App\Http\Controllers\FirmaContratoController::class, 'pdf'])->name('contrato.firma.pdf');
    Route::post('/f/{token}/codigo', [\App\Http\Controllers\FirmaContratoController::class, 'codigo'])
        ->middleware('throttle:firma-codigo')->name('contrato.firma.codigo');
    Route::post('/f/{token}', [\App\Http\Controllers\FirmaContratoController::class, 'firmar'])
        ->middleware('throttle:firma-firmar')->name('contrato.firma.firmar');

    Route::get('/verificar/contrato/{codigo}', [\App\Http\Controllers\VerificarContratoController::class, 'mostrar'])->name('contrato.verificar');
    Route::post('/verificar/contrato/{codigo}', [\App\Http\Controllers\VerificarContratoController::class, 'archivo'])
        ->middleware('throttle:verificar-archivo')->name('contrato.verificar.archivo');
});
