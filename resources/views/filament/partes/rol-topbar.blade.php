@php
    $empresa = filament()->getTenant();
    $usuario = auth()->user();
    $rol = $empresa && $usuario ? \App\Actions\Configuracion\AsignarRol::rolActual($usuario, $empresa)?->name : null;
    $rol ??= $usuario?->es_superadmin ? 'Superadministrador' : null;
@endphp
@if ($rol)
    <span class="ruta-topbar-rol">{{ mb_strtoupper($rol) }}</span>
@endif
