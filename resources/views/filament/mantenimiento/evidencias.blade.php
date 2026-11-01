{{-- Lista de evidencias (facturas, fotos) con miniaturas. Recibe $evidencias. --}}
@php $evidencias = $evidencias ?? collect(); @endphp
@if ($evidencias->isEmpty())
    <p style="font-size: .875rem; color: rgb(100 116 139)">Sin evidencias.</p>
@else
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem">
        @foreach ($evidencias as $e)
            <a href="{{ route('evidencia', $e->id) }}" target="_blank" rel="noopener"
               style="display: block; border: 1px solid rgb(148 163 184 / .35); border-radius: .75rem; overflow: hidden; text-decoration: none; color: inherit">
                @if ($e->esImagen() && ! in_array($e->mime, ['image/heic', 'image/heif'], true))
                    <img src="{{ route('evidencia', $e->id) }}" alt="" loading="lazy" style="width: 100%; height: 110px; object-fit: cover; background: rgb(241 245 249)">
                @else
                    <div style="height: 110px; display: flex; align-items: center; justify-content: center; background: rgb(241 245 249); font-weight: 700; color: rgb(194 65 12)">
                        {{ $e->mime === 'application/pdf' ? 'PDF' : strtoupper(pathinfo($e->nombre, PATHINFO_EXTENSION) ?: 'ARCHIVO') }}
                    </div>
                @endif
                <div style="padding: .4rem .55rem; font-size: .75rem; line-height: 1.3">
                    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600">{{ $e->nombre }}</div>
                    <div style="color: rgb(100 116 139)">{{ $e->tamano_texto }} · {{ $e->created_at->format('d/m/Y') }}</div>
                    @if ($e->descripcion)<div style="color: rgb(100 116 139)">{{ \Illuminate\Support\Str::limit($e->descripcion, 60) }}</div>@endif
                </div>
            </a>
        @endforeach
    </div>
@endif
