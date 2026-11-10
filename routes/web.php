<?php

use App\Http\Controllers\CambiarClaveController;
use App\Http\Controllers\Conductor\AccesoController;
use App\Http\Controllers\Conductor\CargaController;
use App\Http\Controllers\Conductor\MantenimientoController;
use App\Http\Controllers\Conductor\PortalController;
use App\Http\Controllers\Conductor\PreoperacionalController;
use App\Http\Controllers\Conductor\PushController;
use App\Http\Controllers\Conductor\PwaController;
use App\Http\Controllers\Conductor\ViajeController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\FirmaContratoController;
use App\Http\Controllers\ManualPdfController;
use App\Http\Controllers\ReciboAdministracionController;
use App\Http\Controllers\RegistroPasajerosController;
use App\Http\Controllers\VerificarConsultaTransitoController;
use App\Http\Controllers\VerificarContratoController;
use App\Http\Controllers\VerificarFuecController;
use App\Http\Controllers\VerificarInspeccionController;
use App\Http\Controllers\VerificarListadoController;
use App\Http\Controllers\VerificarManifiestoController;
use App\Http\Controllers\VerificarReciboController;
use App\Http\Middleware\SinCachePortal;
use Illuminate\Support\Facades\Route;

// La raíz lleva al panel de empresas (pide inicio de sesión si hace falta).
Route::redirect('/', '/app');

// Verificación pública del FUEC (código QR del documento).
Route::get('/verificar/fuec/{codigo}', VerificarFuecController::class)
    ->where('codigo', '[a-z0-9]{24}')
    ->middleware('throttle:30,1')
    ->name('fuec.verificar');

// Firma electrónica del contrato (enlace enviado por SMS o correo) y su verificación pública.
// Límites por IP con claves separadas (ver AppServiceProvider): leer, pedir código, firmar y verificar.
Route::middleware('throttle:firma')->where(['token' => '[A-Za-z0-9]{22}', 'codigo' => '[a-z0-9]{24}'])->group(function () {
    Route::get('/f/{token}', [FirmaContratoController::class, 'mostrar'])->name('contrato.firma');
    Route::get('/f/{token}/pdf', [FirmaContratoController::class, 'pdf'])->name('contrato.firma.pdf');
    Route::post('/f/{token}/codigo', [FirmaContratoController::class, 'codigo'])
        ->middleware('throttle:firma-codigo')->name('contrato.firma.codigo');
    Route::post('/f/{token}', [FirmaContratoController::class, 'firmar'])
        ->middleware('throttle:firma-firmar')->name('contrato.firma.firmar');

    Route::get('/verificar/contrato/{codigo}', [VerificarContratoController::class, 'mostrar'])->name('contrato.verificar');
    Route::post('/verificar/contrato/{codigo}', [VerificarContratoController::class, 'archivo'])
        ->middleware('throttle:verificar-archivo')->name('contrato.verificar.archivo');

    // Verificación pública del listado de pasajeros de un FUEC (QR).
    Route::get('/verificar/listado/{codigo}', [VerificarListadoController::class, 'mostrar'])->name('listado.verificar');
});

// Autorregistro de pasajeros al listado de un FUEC (el cliente comparte este enlace con los pasajeros).
Route::middleware('throttle:firma')->where(['token' => '[A-Za-z0-9]{32}'])->group(function () {
    Route::get('/p/{token}', [RegistroPasajerosController::class, 'mostrar'])->name('pasajeros.registro');
    Route::post('/p/{token}', [RegistroPasajerosController::class, 'registrar'])
        ->middleware('throttle:firma-firmar')->name('pasajeros.registrar');
});

// Verificación pública de un preoperacional o inspección (código QR del PDF).
Route::get('/verificar/inspeccion/{codigo}', VerificarInspeccionController::class)
    ->where('codigo', '[a-z0-9]{24}')
    ->middleware('throttle:30,1')
    ->name('inspeccion.verificar');

// Verificación pública y descarga de un recibo de caja (QR del recibo y enlace enviado al cliente).
Route::where(['codigo' => '[a-z0-9]{24}'])->middleware('throttle:30,1')->group(function () {
    Route::get('/verificar/recibo/{codigo}', [VerificarReciboController::class, 'mostrar'])->name('recibo.verificar');
    Route::get('/verificar/recibo/{codigo}/pdf', [VerificarReciboController::class, 'pdf'])->name('recibo.pdf');
});

// Verificación pública y descarga del PDF de un manifiesto electrónico de carga (QR).
Route::where(['codigo' => '[a-z0-9]{24}'])->middleware('throttle:30,1')->group(function () {
    Route::get('/verificar/manifiesto/{codigo}', [VerificarManifiestoController::class, 'mostrar'])->name('manifiesto.verificar');
    Route::get('/verificar/manifiesto/{codigo}/pdf', [VerificarManifiestoController::class, 'pdf'])->name('manifiesto.pdf');
});

// Verificación pública y descarga de la constancia de una consulta de tránsito (QR de la constancia).
Route::where(['codigo' => '[a-z0-9]{24}'])->middleware('throttle:30,1')->group(function () {
    Route::get('/verificar/consulta-transito/{codigo}', [VerificarConsultaTransitoController::class, 'mostrar'])->name('consulta-transito.verificar');
    Route::get('/verificar/consulta-transito/{codigo}/pdf', [VerificarConsultaTransitoController::class, 'pdf'])->name('consulta-transito.pdf');
});

// Portal del conductor: ingreso con documento y clave; servicios, preoperacional y extracto.
Route::prefix('conductor')->name('conductor.')->middleware(SinCachePortal::class)->group(function () {
    // PWA (instalable): service worker y página de respaldo sin conexión. Públicos.
    Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('sw');
    Route::get('/offline', [PwaController::class, 'offline'])->name('offline');

    Route::get('/ingresar', [AccesoController::class, 'formulario'])->name('ingresar');
    Route::post('/ingresar', [AccesoController::class, 'ingresar'])
        ->middleware('throttle:conductor-ingreso')->name('ingresar.enviar');

    Route::middleware('conductor')->group(function () {
        Route::get('/', [PortalController::class, 'inicio'])->name('inicio');
        Route::post('/salir', [AccesoController::class, 'salir'])->name('salir');
        // Notificaciones Web Push: suscribir el navegador, darlo de baja y enviar una de prueba.
        Route::post('/push/suscribir', [PushController::class, 'suscribir'])->middleware('throttle:30,1')->name('push.suscribir');
        Route::post('/push/baja', [PushController::class, 'baja'])->name('push.baja');
        Route::post('/push/probar', [PushController::class, 'probar'])->middleware('throttle:10,1')->name('push.probar');
        Route::get('/clave', [AccesoController::class, 'formularioClave'])->name('clave');
        Route::post('/clave', [AccesoController::class, 'cambiarClave'])->name('clave.guardar');
        Route::get('/firma', [PortalController::class, 'formularioFirma'])->name('firma');
        Route::post('/firma', [PortalController::class, 'guardarFirma'])->name('firma.guardar');
        Route::get('/fuec/{fuec}/extracto', [PortalController::class, 'extracto'])->name('extracto');
        Route::get('/fuec/{fuec}/pasajeros', [PortalController::class, 'pasajeros'])->name('pasajeros');
        Route::get('/preoperacional/{vehiculo}', [PreoperacionalController::class, 'formulario'])->name('preoperacional');
        Route::post('/preoperacional/{vehiculo}', [PreoperacionalController::class, 'guardar'])
            ->middleware('throttle:20,1')->name('preoperacional.guardar');
        Route::get('/historial', [PreoperacionalController::class, 'historial'])->name('historial');
        Route::get('/servicio/{servicio}', [ViajeController::class, 'mostrar'])->whereNumber('servicio')->name('servicio');
        Route::get('/servicio/{servicio}/pdf', [ViajeController::class, 'pdf'])->whereNumber('servicio')->name('servicio.pdf');
        Route::post('/servicio/{servicio}/reporte', [ViajeController::class, 'reportar'])->whereNumber('servicio')
            ->middleware('throttle:30,1')->name('servicio.reportar');
        Route::get('/inspeccion/{inspeccion}/pdf', [PreoperacionalController::class, 'pdf'])->name('inspeccion.pdf');
        // Tiempos logísticos: el conductor marca llegada, entrada y salida en cargue y descargue.
        Route::get('/carga', [CargaController::class, 'inicio'])->name('carga');
        Route::post('/carga/remesa/{remesa}', [CargaController::class, 'marcar'])
            ->whereNumber('remesa')->middleware('throttle:60,1')->name('carga.marcar');
        Route::get('/mantenimiento', [MantenimientoController::class, 'inicio'])->name('mantenimiento');
        Route::get('/mantenimiento/{vehiculo}', [MantenimientoController::class, 'formulario'])->name('mantenimiento.registrar');
        // Admite varios archivos: sin tope, un conductor podría llenar el disco.
        Route::post('/mantenimiento/{vehiculo}', [MantenimientoController::class, 'guardar'])
            ->middleware('throttle:20,1')->name('mantenimiento.guardar');
    });
});

// Evidencias (facturas, fotos) de mantenimientos y planes de acción: disco privado, con permiso.
Route::get('/evidencias/{evidencia}', EvidenciaController::class)
    ->middleware('auth')->whereNumber('evidencia')->name('evidencia');

// Descarga del recibo de pago de administración (usuario del panel con permiso y de la empresa).
Route::get('/administracion/recibo/{pago}', ReciboAdministracionController::class)
    ->middleware('auth')->whereNumber('pago')->name('administracion.recibo');

// Manual de la plataforma en PDF (solo superadministrador).
Route::get('/admin/manual/pdf', ManualPdfController::class)
    ->middleware('auth')->name('admin.manual.pdf');

// Cambio de clave obligatorio del panel /app (clave temporal del primer ingreso).
// Ruta plana, fuera del panel, para no depender del enrutado por empresa de Filament.
Route::middleware('auth')->group(function () {
    Route::get('/clave-nueva', [CambiarClaveController::class, 'mostrar'])->name('clave.obligatoria');
    Route::post('/clave-nueva', [CambiarClaveController::class, 'guardar'])
        ->middleware('throttle:10,1')->name('clave.obligatoria.guardar');
});
