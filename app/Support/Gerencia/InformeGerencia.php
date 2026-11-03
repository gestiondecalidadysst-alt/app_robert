<?php

namespace App\Support\Gerencia;

use App\Mail\CorreoInformeGerencia;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use App\Support\Pdf\Imagenes;
use App\Support\Webhooks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resumen de gerencia en PDF (mismos indicadores del tablero) y su envío automático el día 1 de
 * cada mes con el mes anterior: por correo a quienes tienen `ver_gerencia` y por webhook
 * (`gerencia.informe_mensual`) si la empresa lo eligió.
 */
final class InformeGerencia
{
    public static function pdf(Indicadores $i): string
    {
        $empresa = Empresa::query()->with('municipio')->findOrFail($i->empresaId);

        $html = view('pdf.informe-gerencia', [
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'i' => $i,
            'oficina' => $i->oficinaId ? \App\Models\Oficina::query()->withoutGlobalScopes()->find($i->oficinaId)?->nombre : null,
            'flota' => $i->flota(),
            'operacion' => $i->operacion(),
            'cartera' => $i->cartera(),
            'pesv' => $i->pesv(),
            'mantenimiento' => $i->mantenimiento(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function nombre(Indicadores $i): string
    {
        return 'resumen-gerencia-' . $i->desde->format('Y-m-d') . '-a-' . $i->hasta->format('Y-m-d') . '.pdf';
    }

    /**
     * Envía el resumen del mes anterior a la fecha dada.
     *
     * @return array{correos: int, webhook: bool}
     */
    public static function enviarMensual(Empresa $empresa, ?Carbon $fecha = null): array
    {
        $mes = ($fecha ?? today())->copy()->subMonthNoOverflow();
        $i = new Indicadores($empresa->id, $mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());
        $periodo = ucfirst($mes->translatedFormat('F Y'));
        $pdf = base64_encode(self::pdf($i));
        $nombre = $empresa->nombre_comercial ?: $empresa->razon_social;
        $correos = 0;

        foreach (self::destinatarios($empresa) as $usuario) {
            Mensajero::correo($empresa, 'informe_gerencia', $usuario->email,
                new CorreoInformeGerencia($nombre, $usuario->name, $periodo, $pdf, self::nombre($i)));
            $correos++;
        }

        $op = $i->operacion();
        $ca = $i->cartera();
        $se = $i->pesv();
        $ma = $i->mantenimiento();
        $webhook = Webhooks::emitir($empresa, 'gerencia.informe_mensual', [
            'periodo' => $periodo,
            'desde' => $i->desde->toDateString(),
            'hasta' => $i->hasta->toDateString(),
            'extractos' => $op['extractos'],
            'servicios' => $op['servicios'],
            'uso_flota' => $op['uso_flota'],
            'contratado' => $ca['contratado'],
            'recaudado' => $ca['recaudado'],
            'cartera' => $ca['cartera'],
            'cartera_vencida' => $ca['cartera_vencida'],
            'preoperacional_cumplimiento' => $se['preop_cumplimiento'],
            'planes_accion_pendientes' => $se['planes_pendientes'],
            'mantenimiento_cumplimiento' => $ma['programa_cumplimiento'],
            'mantenimiento_costo' => $ma['costo'],
        ]) !== null;

        return ['correos' => $correos, 'webhook' => $webhook];
    }

    /** Usuarios activos de la empresa con permiso de gerencia y correo. */
    public static function destinatarios(Empresa $empresa): \Illuminate\Support\Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        try {
            return $empresa->usuarios()->wherePivot('activo', true)->where('users.activo', true)->whereNotNull('users.email')->get()
                ->filter(fn (User $u) => $u->unsetRelation('roles')->unsetRelation('permissions')->can('ver_gerencia'))
                ->values();
        } finally {
            $registrar->setPermissionsTeamId($anterior);
        }
    }
}
