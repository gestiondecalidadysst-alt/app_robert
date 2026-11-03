<x-filament-panels::page>
    {{ $this->form }}
    <x-filament::section heading="Qué trae cada informe" collapsible collapsed>
        <ul style="margin: 0; padding-left: 1.1rem; font-size: .875rem; line-height: 1.6">
            <li><b>Pagos recibidos:</b> cada recibo de caja con contrato, cliente, tipo (abono, abono final o pago total), método, valor, saldo después y quién lo registró. Incluye los anulados.</li>
            <li><b>Estado de contratos:</b> contratos vigentes en el rango con valor, pagado, saldo, fecha límite y días vencido.</li>
            <li><b>Extractos expedidos:</b> número del FUEC, fecha de expedición, contrato, cliente, vehículo, conductores, vigencia, origen y destino, y si está anulado.</li>
            <li><b>Operación de la flota:</b> una fila por servicio y día con horario, vehículo, conductores, ruta, cliente, extracto y si el conductor leyó el plan.</li>
            <li><b>Preoperacionales:</b> cada preoperacional e inspección con resultado, kilometraje e ítems que no cumplen.</li>
            <li><b>Mantenimientos:</b> fecha, vehículo, tipo, rutinas, establecimiento, kilometraje y valor.</li>
            <li><b>Resumen de gerencia (PDF):</b> los indicadores del tablero para el rango elegido. Llega solo el día 1 de cada mes con el mes anterior.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
