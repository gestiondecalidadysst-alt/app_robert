<x-filament-panels::page>
    <form wire:submit="guardar" class="grid gap-6">
        {{ $this->form }}

        @can('editar_integraciones')
            <div>
                <x-filament::button type="submit">Guardar</x-filament::button>
            </div>
        @endcan
    </form>

    @php $entregas = $this->getEntregas(); @endphp
    @if ($entregas->isNotEmpty())
        <x-filament::section heading="Últimos avisos enviados por webhook" collapsible>
            <div style="overflow-x: auto">
                <table style="width: 100%; font-size: .85rem; border-collapse: collapse">
                    <thead><tr style="text-align: left; color: rgb(100 116 139); font-size: .75rem">
                        <th style="padding: .35rem">Fecha</th><th style="padding: .35rem">Evento</th><th style="padding: .35rem">Estado</th><th style="padding: .35rem">Intentos</th><th style="padding: .35rem">Respuesta</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($entregas as $w)
                            <tr style="border-top: 1px solid rgb(148 163 184 / .2)">
                                <td style="padding: .35rem; white-space: nowrap">{{ $w->created_at->format('d/m/Y h:i a') }}</td>
                                <td style="padding: .35rem">{{ $w->evento }}</td>
                                <td style="padding: .35rem; font-weight: 600; color: {{ $w->estado === 'enviado' ? '#15803d' : ($w->estado === 'fallido' ? '#b91c1c' : '#64748b') }}">{{ ucfirst($w->estado) }}</td>
                                <td style="padding: .35rem">{{ $w->intentos }}</td>
                                <td style="padding: .35rem; color: rgb(100 116 139)">{{ $w->codigo_respuesta ? 'HTTP '.$w->codigo_respuesta : '' }} {{ \Illuminate\Support\Str::limit($w->error, 90) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
