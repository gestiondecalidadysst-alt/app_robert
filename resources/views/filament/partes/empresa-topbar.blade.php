@php($empresa = filament()->getTenant())
@if ($empresa)
    <div class="ruta-topbar-empresa">
        <span class="ruta-topbar-etiqueta">EMPRESA</span>
        <span class="ruta-topbar-nombre">{{ $empresa->getFilamentName() }}</span>
    </div>
@endif
