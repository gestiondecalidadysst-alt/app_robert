<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Módulos que el superadministrador contrata a cada empresa. Lo que no está contratado no aparece
 * en el menú ni abre por dirección: los permisos de sus recursos quedan negados (Gate::before).
 *
 * Siempre incluidos (base): Mi empresa y configuración, flota (vehículos, propietarios, documentos)
 * y conductores; los comparten todos los módulos. Las consultas de tránsito se habilitan aparte.
 */
enum ModuloEmpresa: string implements HasDescription, HasLabel
{
    case Pasajeros = 'pasajeros';
    case SeguridadVial = 'seguridad_vial';
    case Mantenimiento = 'mantenimiento';
    case Gerencia = 'gerencia';
    case Carga = 'carga';

    /** recurso de permiso (Permisos::RECURSOS) => módulo. Los que no están son de la base. */
    public const RECURSOS = [
        'clientes' => 'pasajeros',
        'contratos' => 'pasajeros',
        'pagos_contrato' => 'pasajeros',
        'fuecs' => 'pasajeros',
        'documentos_contrato' => 'pasajeros',
        'plantillas_contrato' => 'pasajeros',
        'empresas_convenio' => 'pasajeros',
        'servicios' => 'pasajeros',
        'rutas' => 'pasajeros',
        'items_chequeo' => 'seguridad_vial',
        'inspecciones' => 'seguridad_vial',
        'mantenimientos' => 'mantenimiento',
        'planes_accion' => 'mantenimiento',
        'rutinas_mantenimiento' => 'mantenimiento',
        'programas_mantenimiento' => 'mantenimiento',
        'gerencia' => 'gerencia',
        'terceros_carga' => 'carga',
        'remolques' => 'carga',
        'rndc' => 'carga',
        'remesas_carga' => 'carga',
        'manifiestos_carga' => 'carga',
        'cumplidos_carga' => 'carga',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Pasajeros => 'Transporte especial de pasajeros',
            self::SeguridadVial => 'Seguridad vial (PESV)',
            self::Mantenimiento => 'Mantenimiento',
            self::Gerencia => 'Gerencia',
            self::Carga => 'Carga (RNDC)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Pasajeros => 'Clientes, contratos con firma y recibos, FUEC, planeador y rutogramas.',
            self::SeguridadVial => 'Preoperacionales e inspecciones, ítems de chequeo; preoperacional en el portal del conductor.',
            self::Mantenimiento => 'Planes de acción, mantenimientos, plan preventivo, programa anual y calendario.',
            self::Gerencia => 'Tablero de gerencia, informes en Excel y resumen mensual.',
            self::Carga => 'Terceros, vehículos de carga y remolques registrados en el RNDC. Próximas fases: remesas, manifiestos y cumplidos.',
        };
    }

    /** Los que tiene una empresa creada antes de existir los módulos (todo lo construido). */
    public static function porDefecto(): array
    {
        return [self::Pasajeros->value, self::SeguridadVial->value, self::Mantenimiento->value, self::Gerencia->value];
    }

    public static function deRecurso(string $recurso): ?self
    {
        return isset(self::RECURSOS[$recurso]) ? self::from(self::RECURSOS[$recurso]) : null;
    }

    public function disponible(): bool
    {
        return true;
    }
}
