<?php

namespace App\Support\Alertas;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\EstadoAdministracion\EstadoAdministracionResource;
use App\Filament\App\Resources\Vencimientos\VencimientoResource;
use App\Filament\App\Resources\VencimientosConductores\VencimientoConductorResource;
use App\Models\Conductor;
use App\Models\CuotaAdministracion;
use App\Models\DocumentoConductor;
use App\Models\DocumentoVehiculo;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reúne en un solo lugar lo que requiere atención de una empresa: documentos de vehículos y de
 * conductores por vencer o vencidos, y la administración en mora. Lo usan la bandeja del inicio
 * (conteos con enlace) y el comando de avisos (registros para el correo al propietario/conductor).
 *
 * Las consultas filtran siempre por la empresa de forma explícita (deEmpresa) para servir tanto
 * dentro del panel como en la consola.
 */
final class CentroAlertas
{
    /** Documentos vigentes de vehículos en flota que están por vencer o vencidos. */
    public static function documentosVehiculo(Empresa $empresa): Builder
    {
        return DocumentoVehiculo::query()
            ->deEmpresa($empresa)
            ->vigentes()
            ->enAtencion()
            ->whereHas('vehiculo', fn (Builder $q) => $q->enFlota());
    }

    /** Documentos obligatorios vigentes de conductores vinculados, por vencer o vencidos. */
    public static function documentosConductor(Empresa $empresa): Builder
    {
        return DocumentoConductor::query()
            ->deEmpresa($empresa)
            ->vigentes()
            ->enAtencion()
            ->whereIn('tipo', array_map(fn ($t) => $t->value, Conductor::tiposObligatorios()))
            ->whereHas('conductor', fn (Builder $q) => $q->vinculados());
    }

    /** Cuotas de administración en mora (periodo anterior al mes en curso y con saldo). */
    public static function cuotasEnMora(Empresa $empresa): Builder
    {
        return CuotaAdministracion::query()
            ->deEmpresa($empresa)
            ->whereNull('anulado_at')
            ->whereColumn('pagado', '<', 'valor')
            ->whereDate('periodo', '<', now()->startOfMonth()->toDateString());
    }

    public static function administracionActiva(Empresa $empresa): bool
    {
        return Modulos::activo($empresa, ModuloEmpresa::Administracion);
    }

    /**
     * Grupos para la bandeja del inicio: cada uno con su conteo y enlace, filtrados por lo que el
     * usuario puede ver. Solo se devuelven los grupos con algo que atender.
     *
     * @return list<array{clave:string, titulo:string, total:int, icono:string, color:string, url:string}>
     */
    public static function grupos(Empresa $empresa, User $usuario): array
    {
        $grupos = [];

        if ($usuario->can('ver_documentos_vehiculo')) {
            $total = self::documentosVehiculo($empresa)->count();
            if ($total > 0) {
                $grupos[] = [
                    'clave' => 'docs_vehiculo',
                    'titulo' => 'Documentos de vehículos',
                    'total' => $total,
                    'icono' => 'heroicon-o-truck',
                    'color' => self::colorDocumentos(self::documentosVehiculo($empresa)),
                    'url' => VencimientoResource::getUrl(),
                ];
            }
        }

        if ($usuario->can('ver_documentos_conductor')) {
            $total = self::documentosConductor($empresa)->count();
            if ($total > 0) {
                $grupos[] = [
                    'clave' => 'docs_conductor',
                    'titulo' => 'Documentos de conductores',
                    'total' => $total,
                    'icono' => 'heroicon-o-identification',
                    'color' => self::colorDocumentos(self::documentosConductor($empresa)),
                    'url' => VencimientoConductorResource::getUrl(),
                ];
            }
        }

        if (self::administracionActiva($empresa) && $usuario->can('ver_pagos_administracion')) {
            $total = self::cuotasEnMora($empresa)->distinct('vehiculo_id')->count('vehiculo_id');
            if ($total > 0) {
                $grupos[] = [
                    'clave' => 'administracion_mora',
                    'titulo' => 'Administración en mora',
                    'total' => $total,
                    'icono' => 'heroicon-o-building-library',
                    'color' => 'danger',
                    'url' => EstadoAdministracionResource::getUrl(),
                ];
            }
        }

        return $grupos;
    }

    /** Rojo si hay algo vencido; ámbar si solo hay por vencer. */
    private static function colorDocumentos(Builder $consulta): string
    {
        return (clone $consulta)->vencidos()->exists() ? 'danger' : 'warning';
    }
}
