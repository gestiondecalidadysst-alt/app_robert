<nav class="menu">
    <a class="{{ request()->routeIs('conductor.inicio') ? 'activo' : '' }}" href="{{ route('conductor.inicio') }}">Servicios</a>
    <a class="{{ request()->routeIs('conductor.historial') ? 'activo' : '' }}" href="{{ route('conductor.historial') }}">Preoperacionales</a>
    <a class="{{ request()->routeIs('conductor.mantenimiento*') ? 'activo' : '' }}" href="{{ route('conductor.mantenimiento') }}">Mantenimiento</a>
</nav>
