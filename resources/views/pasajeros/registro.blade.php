<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Registro de pasajero</title>
<style>
    :root{--tinta:#15312a;--lima:#5e9a24;--borde:#e4e8e4;--gris:#6b7772;--err:#b91c1c;}
    *{box-sizing:border-box;}
    body{margin:0;background:#eef1ee;color:#16201c;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.45;}
    .wrap{max-width:460px;margin:0 auto;padding:18px 16px 40px;}
    .card{background:#fff;border:1px solid var(--borde);border-radius:14px;overflow:hidden;}
    .top{background:var(--tinta);color:#fff;padding:16px 18px;}
    .top .emp{font-size:12px;color:#aec6ba;letter-spacing:.3px;}
    .top h1{margin:4px 0 0;font-size:17px;font-weight:700;}
    .top .via{margin-top:8px;font-size:13px;color:#d9e7de;}
    .top .fechas{margin-top:3px;font-size:12px;color:#aec6ba;}
    .body{padding:18px;}
    label{display:block;font-size:12px;font-weight:700;color:var(--gris);text-transform:uppercase;letter-spacing:.3px;margin:14px 0 5px;}
    input,select{width:100%;padding:11px 12px;font-size:15px;border:1px solid #cdd4ce;border-radius:9px;background:#fff;color:#16201c;}
    input:focus,select:focus{outline:none;border-color:var(--tinta);}
    .row{display:flex;gap:10px;}
    .row .t{flex:0 0 38%;} .row .d{flex:1;}
    .check{display:flex;align-items:center;gap:9px;margin-top:16px;}
    .check input{width:18px;height:18px;}
    .check label{margin:0;text-transform:none;font-size:14px;color:#16201c;font-weight:500;letter-spacing:0;}
    .menor{display:none;border-left:3px solid var(--borde);padding-left:12px;margin-top:6px;}
    .menor.on{display:block;}
    .btn{width:100%;margin-top:20px;padding:13px;font-size:15px;font-weight:700;color:#fff;background:var(--lima);border:none;border-radius:10px;cursor:pointer;}
    .hint{font-size:12px;color:var(--gris);margin-top:14px;text-align:center;}
    .err{color:var(--err);font-size:12.5px;margin-top:5px;}
    .cupo{font-size:12px;color:var(--gris);margin-top:6px;}
    .estado{text-align:center;padding:26px 20px;}
    .estado .ic{width:58px;height:58px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:30px;}
    .ok .ic{background:#e7f3da;color:#3f7a16;} .warn .ic{background:#fde7e7;color:#b91c1c;} .info .ic{background:#e7eef3;color:#1f5e8a;}
    .estado h2{margin:0 0 6px;font-size:18px;}
    .estado p{margin:0;color:var(--gris);font-size:14px;}
    .again{display:inline-block;margin-top:18px;color:var(--tinta);font-weight:700;text-decoration:none;font-size:14px;border:1px solid var(--borde);padding:9px 16px;border-radius:9px;}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="top">
            @if ($empresa)<div class="emp">{{ $empresa }}</div>@endif
            <h1>Registro de pasajeros</h1>
            @if ($ruta)<div class="via">{{ $ruta }}</div>@endif
            @if ($fechaInicio)<div class="fechas">Viaje: {{ $fechaInicio }}@if ($fechaFin && $fechaFin !== $fechaInicio) al {{ $fechaFin }}@endif</div>@endif
        </div>

        @php($estado = session('estado'))

        @if ($estado === 'ok')
            <div class="estado ok">
                <div class="ic">&#10003;</div>
                <h2>¡Listo, quedaste registrado!</h2>
                <p>Tus datos se agregaron al listado de este viaje. Puedes cerrar esta página.</p>
                @if ($abierto)<a class="again" href="{{ route('pasajeros.registro', $token) }}">Registrar a otra persona</a>@endif
            </div>
        @elseif ($estado === 'duplicado')
            <div class="estado info">
                <div class="ic">&#8505;</div>
                <h2>Ese documento ya está registrado</h2>
                <p>Ese número de documento ya aparece en el listado de este viaje.</p>
                <a class="again" href="{{ route('pasajeros.registro', $token) }}">Registrar a otra persona</a>
            </div>
        @elseif ($estado === 'cerrado' || ! $abierto)
            <div class="estado warn">
                <div class="ic">&#128274;</div>
                <h2>El listado está cerrado</h2>
                <p>La empresa ya cerró el registro de pasajeros para este viaje. Si necesitas incluir a alguien, comunícate con quien te compartió el enlace.</p>
            </div>
        @elseif ($estado === 'lleno' || ($cupos !== null && $cupos <= 0))
            <div class="estado warn">
                <div class="ic">&#128100;</div>
                <h2>El listado está completo</h2>
                <p>Ya se alcanzó la capacidad del vehículo para este viaje.</p>
            </div>
        @else
            <form class="body" method="POST" action="{{ route('pasajeros.registrar', $token) }}">
                @csrf
                <label for="nombre">Nombre completo</label>
                <input id="nombre" name="nombre" value="{{ old('nombre') }}" autocomplete="name" required>
                @error('nombre')<div class="err">{{ $message }}</div>@enderror

                <label>Documento de identidad</label>
                <div class="row">
                    <div class="t">
                        <select name="tipo_documento" aria-label="Tipo de documento">
                            @foreach ($tipos as $cod => $nom)
                                <option value="{{ $cod }}" @selected(old('tipo_documento', 'CC') === $cod)>{{ $cod }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d">
                        <input name="documento" value="{{ old('documento') }}" inputmode="numeric" placeholder="Número" required>
                    </div>
                </div>
                @error('documento')<div class="err">{{ $message }}</div>@enderror

                <label for="telefono">Teléfono (opcional)</label>
                <input id="telefono" name="telefono" value="{{ old('telefono') }}" inputmode="tel" autocomplete="tel">

                <div class="check">
                    <input type="checkbox" id="es_menor" name="es_menor" value="1" @checked(old('es_menor')) onchange="document.getElementById('menor').classList.toggle('on', this.checked)">
                    <label for="es_menor">El pasajero es menor de edad</label>
                </div>
                <div class="menor {{ old('es_menor') ? 'on' : '' }}" id="menor">
                    <label for="acudiente_nombre">Nombre del acudiente</label>
                    <input id="acudiente_nombre" name="acudiente_nombre" value="{{ old('acudiente_nombre') }}">
                    @error('acudiente_nombre')<div class="err">{{ $message }}</div>@enderror
                    <label for="acudiente_documento">Documento del acudiente</label>
                    <input id="acudiente_documento" name="acudiente_documento" value="{{ old('acudiente_documento') }}" inputmode="numeric">
                    @error('acudiente_documento')<div class="err">{{ $message }}</div>@enderror
                </div>

                <button class="btn" type="submit">Registrarme</button>
                @if ($cupos !== null)<div class="cupo">Cupos disponibles: {{ $cupos }}</div>@endif
                <div class="hint">Cada persona debe registrarse una vez. Tus datos solo se usan para el listado de pasajeros de este viaje.</div>
            </form>
        @endif
    </div>
</div>
</body>
</html>
