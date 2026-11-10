@php $nombresFila = ['alta' => 'Alta (SMS y correos)', 'default' => 'Normal', 'baja' => 'Baja (webhooks)']; @endphp
<x-filament-panels::page>
    @if ($urlSinConfigurar)
        <div style="border: 2px solid rgb(185 28 28); border-radius: .75rem; padding: .85rem 1rem; color: rgb(127 29 29); background: rgb(254 242 242)">
            <strong>APP_URL está en «{{ $urlSinConfigurar }}».</strong>
            Los enlaces de los SMS y correos que envía la cola, los QR y los archivos públicos apuntan a esa dirección y no funcionan.
            En Easypanel, agregue <code>APP_URL=https://su-dominio</code> en las variables de entorno del servicio web, del worker y del scheduler, y redespliegue.
        </div>
    @endif
    @php $fallando = collect($salud)->where('ok', false)->count(); @endphp
    <x-filament::section
        :heading="'Salud de los servicios' . ($fallando ? ' · ' . $fallando . ' con problemas' : '')"
        description="Chequeo en vivo de la app y de los servicios de los que depende. Un monitor externo puede consultarlo en /salud."
        collapsible
        :collapsed="$fallando === 0"
    >
        <div style="display: grid; gap: .5rem">
            @foreach ($salud as $c)
                <div style="display: grid; grid-template-columns: 1.5rem 14rem 1fr; gap: .6rem; align-items: start; padding: .35rem 0; border-bottom: 1px solid rgba(148,163,184,.15)">
                    <x-filament::icon
                        :icon="$c['ok'] ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'"
                        @class(['fi-icon'])
                        style="width:1.15rem;height:1.15rem;color: {{ $c['ok'] ? 'rgb(22 163 74)' : 'rgb(220 38 38)' }}"
                    />
                    <strong style="font-size: .875rem">{{ $c['nombre'] }}</strong>
                    <span style="font-size: .8rem; color: rgb(100 116 139)">{{ $c['detalle'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr))">
        @foreach ($procesos as [$nombre, $ultimo, $vivo, $ayuda])
            <x-filament::section>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: .5rem">
                    <strong>{{ $nombre }}</strong>
                    <x-filament::badge :color="$vivo ? 'success' : 'danger'" :icon="$vivo ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'">
                        {{ $vivo ? 'Funcionando' : ($ultimo ? 'Detenido' : 'Sin señales') }}
                    </x-filament::badge>
                </div>
                <p style="margin: .5rem 0 0; font-size: .85rem">Última señal: <b>{{ $ultimo ? $ultimo->diffForHumans() . ' (' . $ultimo->format('d/m h:i:s a') . ')' : 'nunca' }}</b></p>
                <p style="margin: .25rem 0 0; font-size: .75rem; color: rgb(100 116 139)">{{ $ayuda }}</p>
            </x-filament::section>
        @endforeach
        <x-filament::section>
            <strong>Cola</strong>
            <table style="width: 100%; font-size: .85rem; margin-top: .4rem">
                @foreach ($filas as $fila => $f)
                    <tr>
                        <td style="padding: .15rem 0">{{ $nombresFila[$fila] ?? $fila }}</td>
                        <td style="text-align: right"><b>{{ $f['pendientes'] }}</b></td>
                        <td style="text-align: right; color: rgb(100 116 139); font-size: .75rem">{{ $f['mas_antiguo'] ? 'desde ' . $f['mas_antiguo']->diffForHumans() : '' }}</td>
                    </tr>
                @endforeach
            </table>
            @if ($atascados)
                <p style="margin: .4rem 0 0; color: rgb(185 28 28); font-size: .8rem; font-weight: 600">{{ $atascados }} esperando hace más de 10 minutos.</p>
            @endif
        </x-filament::section>
        <x-filament::section>
            <strong>Envíos</strong>
            <table style="width: 100%; font-size: .85rem; margin-top: .4rem">
                @foreach ($envios as $nombre => $n)
                    <tr><td style="padding: .15rem 0">{{ $nombre }}</td><td style="text-align: right; font-weight: 700; color: {{ $n ? 'rgb(185 28 28)' : 'inherit' }}">{{ $n }}</td></tr>
                @endforeach
            </table>
            <p style="margin: .4rem 0 0; font-size: .75rem; color: rgb(100 116 139)">El detalle de cada envío está en el historial de mensajes y en Integraciones de cada empresa.</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="'Trabajos fallidos (' . $fallidos->count() . ')'" description="Agotaron sus reintentos. Al reintentar vuelven a la cola; descartar los borra." collapsible>
        @if ($fallidos->isEmpty())
            <p style="margin: 0; color: rgb(100 116 139); font-size: .875rem">No hay trabajos fallidos.</p>
        @else
            <div style="margin-bottom: .6rem"><x-filament::button size="sm" color="warning" icon="heroicon-m-arrow-path" wire:click="reintentarTodos" wire:confirm="¿Devolver todos los trabajos fallidos a la cola?">Reintentar todos</x-filament::button></div>
            <div style="overflow-x: auto">
                <table style="width: 100%; font-size: .82rem; border-collapse: collapse">
                    <thead><tr style="text-align: left; color: rgb(100 116 139)"><th style="padding: .3rem">Fecha</th><th>Trabajo</th><th>Fila</th><th>Error</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($fallidos as $f)
                            <tr style="border-top: 1px solid rgba(148, 163, 184, .25)" wire:key="f-{{ $f->uuid }}">
                                <td style="padding: .35rem; white-space: nowrap">{{ $f->fecha->format('d/m h:i a') }}</td>
                                <td><b>{{ $f->trabajo }}</b></td>
                                <td>{{ $f->fila }}</td>
                                <td style="color: rgb(185 28 28)">{{ $f->error }}</td>
                                <td style="white-space: nowrap; text-align: right">
                                    <x-filament::link tag="button" size="sm" wire:click="reintentar('{{ $f->uuid }}')">Reintentar</x-filament::link>
                                    <x-filament::link tag="button" size="sm" color="danger" wire:click="descartar('{{ $f->uuid }}')" wire:confirm="¿Descartar este trabajo?" style="margin-left: .6rem">Descartar</x-filament::link>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
