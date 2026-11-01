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

// Verificación pública de un preoperacional o inspección (código QR del PDF).
Route::get('/verificar/inspeccion/{codigo}', \App\Http\Controllers\VerificarInspeccionController::class)
    ->where('codigo', '[a-z0-9]{24}')
    ->middleware('throttle:30,1')
    ->name('inspeccion.verificar');

// Portal del conductor: ingreso con documento y clave; servicios, preoperacional y extracto.
Route::prefix('conductor')->name('conductor.')->group(function () {
    Route::get('/ingresar', [\App\Http\Controllers\Conductor\AccesoController::class, 'formulario'])->name('ingresar');
    Route::post('/ingresar', [\App\Http\Controllers\Conductor\AccesoController::class, 'ingresar'])
        ->middleware('throttle:conductor-ingreso')->name('ingresar.enviar');

    Route::middleware('conductor')->group(function () {
        Route::get('/', [\App\Http\Controllers\Conductor\PortalController::class, 'inicio'])->name('inicio');
        Route::post('/salir', [\App\Http\Controllers\Conductor\AccesoController::class, 'salir'])->name('salir');
        Route::get('/clave', [\App\Http\Controllers\Conductor\AccesoController::class, 'formularioClave'])->name('clave');
        Route::post('/clave', [\App\Http\Controllers\Conductor\AccesoController::class, 'cambiarClave'])->name('clave.guardar');
        Route::get('/firma', [\App\Http\Controllers\Conductor\PortalController::class, 'formularioFirma'])->name('firma');
        Route::post('/firma', [\App\Http\Controllers\Conductor\PortalController::class, 'guardarFirma'])->name('firma.guardar');
        Route::get('/fuec/{fuec}/extracto', [\App\Http\Controllers\Conductor\PortalController::class, 'extracto'])->name('extracto');
        Route::get('/preoperacional/{vehiculo}', [\App\Http\Controllers\Conductor\PreoperacionalController::class, 'formulario'])->name('preoperacional');
        Route::post('/preoperacional/{vehiculo}', [\App\Http\Controllers\Conductor\PreoperacionalController::class, 'guardar'])->name('preoperacional.guardar');
        Route::get('/historial', [\App\Http\Controllers\Conductor\PreoperacionalController::class, 'historial'])->name('historial');
        Route::get('/inspeccion/{inspeccion}/pdf', [\App\Http\Controllers\Conductor\PreoperacionalController::class, 'pdf'])->name('inspeccion.pdf');
    });
});
