<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;flex-direction:column;gap:1.25rem">
            <div>
                <p style="font-size:13px;font-weight:600" class="text-gray-500 dark:text-gray-400">{{ now()->translatedFormat('l j \d\e F') }}</p>
                <h2 style="font-size:1.6rem;font-weight:800;letter-spacing:-.02em;margin:0" class="text-gray-950 dark:text-white">
                    Hola, {{ \Illuminate\Support\Str::of($usuario->name)->before(' ') }}
                </h2>
                <p style="margin:.25rem 0 0" class="text-gray-600 dark:text-gray-300">
                    Estás en <strong>{{ $empresa->getFilamentName() }}</strong>. Accesos rápidos a lo que más usa.
                </p>
            </div>

            @if ($accesos->isNotEmpty())
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:.75rem">
                    @foreach ($accesos as [$titulo, $detalle, $url])
                        <a href="{{ $url }}" class="ruta-acceso">
                            <span class="ruta-acceso-titulo">{{ $titulo }}</span>
                            <span class="ruta-acceso-detalle">{{ $detalle }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
