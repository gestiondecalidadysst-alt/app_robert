<?php

use Illuminate\Support\Facades\Route;

// La raíz lleva al panel de empresas (pide inicio de sesión si hace falta).
Route::redirect('/', '/app');

// Verificación pública del FUEC (código QR del documento).
Route::get('/verificar/fuec/{codigo}', \App\Http\Controllers\VerificarFuecController::class)
    ->where('codigo', '[a-z0-9]{24}')
    ->middleware('throttle:30,1')
    ->name('fuec.verificar');
