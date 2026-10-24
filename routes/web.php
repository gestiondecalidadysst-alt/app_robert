<?php

use Illuminate\Support\Facades\Route;

// La raíz lleva al panel de empresas (pide inicio de sesión si hace falta).
Route::redirect('/', '/app');
