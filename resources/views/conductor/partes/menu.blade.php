<nav class="menu">
    <a class="{{ request()->routeIs('conductor.inicio') ? 'activo' : '' }}" href="{{ route('conductor.inicio') }}">Servicios</a>
    @if (\App\Support\Pesv\ServiciosConductor::conModulo(auth()->user(), \App\Enums\ModuloEmpresa::SeguridadVial))
        <a class="{{ request()->routeIs('conductor.historial') ? 'activo' : '' }}" href="{{ route('conductor.historial') }}">Preoperacionales</a>
    @endif
    @if (\App\Support\Pesv\ServiciosConductor::conModulo(auth()->user(), \App\Enums\ModuloEmpresa::Mantenimiento))
        <a class="{{ request()->routeIs('conductor.mantenimiento*') ? 'activo' : '' }}" href="{{ route('conductor.mantenimiento') }}">Mantenimiento</a>
    @endif
</nav>
