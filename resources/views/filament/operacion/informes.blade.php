<x-filament-panels::page>
    {{ $this->form }}
    <x-filament::section heading="Qué trae cada informe" collapsible collapsed>
        <ul style="margin: 0; padding-left: 1.1rem; font-size: .875rem; line-height: 1.6">
            <li><b>Operación de la flota:</b> una fila por servicio y día con horario, vehículo, conductores, ruta, cliente, extracto, si el conductor leyó el plan y los kilómetros.</li>
            <li><b>Extractos expedidos:</b> número del FUEC, fecha de expedición, contrato, cliente, vehículo, conductores, vigencia, origen y destino, y si está anulado.</li>
        </ul>
        <p style="margin: .75rem 0 0; font-size: .8rem; color: #6b7280">En PDF salen con el mismo encabezado y pie del resto de informes del sistema.</p>
    </x-filament::section>
</x-filament-panels::page>
