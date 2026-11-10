<x-filament-panels::page>
    @if ($grupos->isEmpty())
        <x-filament::section>
            <div class="text-center py-10">
                <p class="text-gray-500 dark:text-gray-400">Aún no hay secciones en el manual.</p>
                <x-filament::button tag="a" :href="\App\Filament\Admin\Resources\Manual\SeccionManualResource::getUrl('create')" class="mt-4">
                    Crear la primera sección
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        <div x-data="{ q: '' }">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6">

                {{-- Índice + buscador --}}
                <aside class="lg:sticky lg:top-20 self-start space-y-4">
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" x-model="q" placeholder="Buscar en el manual..." />
                    </x-filament::input.wrapper>

                    <nav class="space-y-4 text-sm">
                        @foreach ($grupos as $grupo => $secciones)
                            <div x-show="$el.querySelectorAll('[data-item]:not([style*=none])').length > 0 || q === ''">
                                <p class="font-semibold text-gray-700 dark:text-gray-200 uppercase text-xs tracking-wide mb-1">{{ $grupo }}</p>
                                <ul class="space-y-1">
                                    @foreach ($secciones as $seccion)
                                        <li data-item
                                            x-show="q === '' || '{{ \Illuminate\Support\Str::lower($seccion->titulo) }}'.includes(q.toLowerCase())">
                                            <a href="#sec-{{ $seccion->slug }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                                {{ $seccion->titulo }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </nav>
                </aside>

                {{-- Contenido --}}
                <div class="space-y-6">
                    @foreach ($grupos as $grupo => $secciones)
                        @foreach ($secciones as $seccion)
                            @php
                                $texto = \Illuminate\Support\Str::lower($seccion->titulo.' '.strip_tags((string) $seccion->contenido));
                            @endphp
                            <section id="sec-{{ $seccion->slug }}"
                                     data-texto="{{ \Illuminate\Support\Str::limit($texto, 3000, '') }}"
                                     x-show="q === '' || $el.dataset.texto.includes(q.toLowerCase())"
                                     class="scroll-mt-24">
                                <x-filament::section>
                                    <x-slot name="heading">{{ $seccion->titulo }}</x-slot>
                                    <x-slot name="description">{{ $grupo }}</x-slot>
                                    <x-slot name="headerEnd">
                                        <x-filament::link :href="$editarUrl($seccion)" icon="heroicon-m-pencil-square" size="sm" color="gray">
                                            Editar
                                        </x-filament::link>
                                    </x-slot>

                                    <div class="prose prose-sm dark:prose-invert max-w-none">
                                        {!! $seccion->contenido ?: '<p class="text-gray-400">Sección sin contenido todavía.</p>' !!}
                                    </div>
                                </x-filament::section>
                            </section>
                        @endforeach
                    @endforeach

                    <p x-show="q !== ''" x-cloak class="text-sm text-gray-400">
                        Mostrando resultados para «<span x-text="q"></span>».
                    </p>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
