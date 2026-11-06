{{-- Informe tabular con el aspecto estándar del sistema: encabezado y pie fijos en cada
     página (con número de página), mismo estilo que las hojas de vida. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 112px 34px 70px 34px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; }

    header { position: fixed; top: -92px; left: 0; right: 0; height: 80px; }
    .cab { border-bottom: 2px solid #1B2B3A; padding-bottom: 8px; }
    .cab table { width: 100%; }
    .cab .logo { height: 44px; }
    .cab .marca { font-size: 14px; font-weight: bold; color: #1B2B3A; }
    .cab .sub { font-size: 9px; color: #6b7280; }
    .cab .titulo { text-align: right; }
    .cab .titulo .t { font-size: 12px; font-weight: bold; color: #C2410C; }
    .cab .titulo .r { font-size: 9px; color: #374151; }

    footer { position: fixed; bottom: -54px; left: 0; right: 0; height: 44px;
             border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #6b7280; }
    footer table { width: 100%; }
    footer .pagina:after { content: "Página " counter(page) " de " counter(pages); }

    table.datos { width: 100%; border-collapse: collapse; }
    table.datos th { background: #1B2B3A; color: #fff; font-size: 8px; text-align: left; padding: 5px 6px; }
    table.datos td { padding: 4px 6px; border-bottom: 1px solid #eef1f4; font-size: 8.5px; vertical-align: top; }
    table.datos tr { page-break-inside: avoid; }
    table.datos tbody tr:nth-child(even) td { background: #fafbfc; }
    .der { text-align: right; }
    .vacio { color: #9ca3af; font-style: italic; padding: 10px 4px; }
    .total { font-size: 9px; color: #374151; margin-top: 8px; }
</style>
</head>
<body>

<header>
    <div class="cab">
        <table>
            <tr>
                <td style="width: 58%">
                    @if ($logo)<img src="{{ $logo }}" class="logo"><br>@endif
                    <span class="marca">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</span>
                    <div class="sub">NIT {{ $empresa->nit }}{{ $empresa->dv ? '-'.$empresa->dv : '' }}</div>
                </td>
                <td class="titulo">
                    <div class="t">{{ $titulo }}</div>
                    <div class="r">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</div>
                    @if ($oficina)<div class="sub">Oficina: {{ $oficina }}</div>@endif
                </td>
            </tr>
        </table>
    </div>
</header>

<footer>
    <table>
        <tr>
            <td>Generado el {{ now()->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}</td>
            <td style="text-align: right" class="pagina"></td>
        </tr>
    </table>
</footer>

<main>
    <table class="datos">
        <thead>
            <tr>
                @foreach ($columnas as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>
                    @foreach ($f as $valor)
                        <td class="{{ is_numeric($valor) ? 'der' : '' }}">
                            @if ($valor === null || $valor === '')
                                —
                            @elseif (is_numeric($valor) && ! is_string($valor))
                                {{ number_format((float) $valor, fmod((float) $valor, 1) == 0.0 ? 0 : 2, ',', '.') }}
                            @else
                                {{ $valor }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(1, count($columnas)) }}" class="vacio">Sin datos en el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">Total de registros: {{ count($filas) }}</div>
</main>

</body>
</html>
