<?php

namespace App\Support;

use App\Models\Rol;

/**
 * Catálogo de permisos de la plataforma. Cada permiso se llama "<accion>_<recurso>".
 * Al agregar un módulo nuevo se agregan aquí sus recursos y se ejecuta
 * `php artisan db:seed --class=PermisosSeeder` para crearlos.
 */
final class Permisos
{
    public const ACCIONES = [
        'ver' => 'Ver',
        'crear' => 'Crear',
        'editar' => 'Editar',
        'eliminar' => 'Eliminar',
        'anular' => 'Anular',
        'aprobar' => 'Aprobar',
    ];

    /** recurso => [etiqueta, módulo, acciones permitidas] */
    public const RECURSOS = [
        'empresa' => ['Mi empresa', 'Configuración', ['ver', 'editar']],
        'oficinas' => ['Oficinas', 'Configuración', ['ver', 'crear', 'editar', 'eliminar']],
        'usuarios' => ['Usuarios', 'Configuración', ['ver', 'crear', 'editar', 'eliminar']],
        'roles' => ['Roles y permisos', 'Configuración', ['ver', 'crear', 'editar', 'eliminar']],
        'valores_administracion' => ['Valor de administración', 'Configuración', ['ver', 'crear', 'editar', 'eliminar']],
        'empresas_convenio' => ['Empresas en convenio', 'Configuración', ['ver', 'crear', 'editar', 'eliminar']],
        'auditoria' => ['Auditoría', 'Configuración', ['ver']],
        'plantillas_contrato' => ['Plantilla de contrato', 'Configuración', ['ver', 'crear']],
        'integraciones' => ['Integraciones (SMS y firma)', 'Configuración', ['ver', 'editar']],
        'propietarios' => ['Propietarios', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'vehiculos' => ['Vehículos', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'documentos_vehiculo' => ['Documentos de vehículos', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'novedades_vehiculo' => ['Novedades y estado de vehículos', 'Flota', ['ver', 'crear']],
        'conductores' => ['Conductores', 'Conductores', ['ver', 'crear', 'editar', 'eliminar']],
        'documentos_conductor' => ['Documentos de conductores', 'Conductores', ['ver', 'crear', 'editar', 'eliminar']],
        'novedades_conductor' => ['Novedades y estado de conductores', 'Conductores', ['ver', 'crear']],
        'asignaciones_vehiculo' => ['Asignación de vehículos', 'Conductores', ['ver', 'crear', 'editar']],
        'clientes' => ['Clientes', 'Operación', ['ver', 'crear', 'editar', 'eliminar']],
        'contratos' => ['Contratos', 'Operación', ['ver', 'crear', 'editar', 'anular']],
        'pagos_contrato' => ['Pagos de contratos y recibos', 'Operación', ['ver', 'crear', 'anular']],
        'fuecs' => ['FUEC (extractos)', 'Operación', ['ver', 'crear', 'anular']],
        'documentos_contrato' => ['Contratos: emisión y firma', 'Operación', ['ver', 'crear', 'anular']],
        'servicios' => ['Planeador de servicios', 'Operación', ['ver', 'crear', 'editar', 'anular']],
        'rutas' => ['Rutogramas', 'Operación', ['ver', 'crear', 'editar', 'eliminar']],
        'items_chequeo' => ['Ítems de chequeo (PESV)', 'Seguridad vial', ['ver', 'crear', 'editar', 'eliminar']],
        'inspecciones' => ['Preoperacionales e inspecciones', 'Seguridad vial', ['ver', 'crear']],
        'portal_conductor' => ['Acceso de conductores al portal', 'Conductores', ['crear']],
        'mantenimientos' => ['Mantenimientos', 'Mantenimiento', ['ver', 'crear', 'anular']],
        'planes_accion' => ['Planes de acción', 'Mantenimiento', ['ver', 'crear', 'editar']],
        'rutinas_mantenimiento' => ['Plantillas y planes de mantenimiento', 'Mantenimiento', ['ver', 'crear', 'editar', 'eliminar']],
        'programas_mantenimiento' => ['Programa anual de mantenimiento', 'Mantenimiento', ['ver', 'crear', 'editar', 'aprobar']],
    ];

    /** Lista plana de todos los nombres de permiso. */
    public static function todos(): array
    {
        $permisos = [];
        foreach (self::RECURSOS as $recurso => [, , $acciones]) {
            foreach ($acciones as $accion) {
                $permisos[] = "{$accion}_{$recurso}";
            }
        }

        return $permisos;
    }

    /** Etiqueta legible: "Crear · Oficinas". */
    public static function etiqueta(string $permiso): string
    {
        [$accion, $recurso] = explode('_', $permiso, 2);

        return (self::ACCIONES[$accion] ?? $accion) . ' · ' . (self::RECURSOS[$recurso][0] ?? $recurso);
    }

    /** Permisos agrupados por recurso, para la matriz de la pantalla de roles. */
    public static function agrupados(): array
    {
        $grupos = [];
        foreach (self::RECURSOS as $recurso => [$etiqueta, $modulo, $acciones]) {
            foreach ($acciones as $accion) {
                $grupos["{$modulo} · {$etiqueta}"]["{$accion}_{$recurso}"] = self::ACCIONES[$accion];
            }
        }

        return $grupos;
    }

    /** Permisos por defecto de los roles que se crean con cada empresa. */
    public static function porRol(): array
    {
        $todos = self::todos();
        // Gerencia ve todo menos las integraciones (credenciales).
        $soloVer = array_values(array_filter($todos, fn (string $p) => str_starts_with($p, 'ver_') && $p !== 'ver_integraciones'));

        return [
            Rol::ADMINISTRADOR => $todos,
            Rol::GERENCIA => [...$soloVer, 'aprobar_programas_mantenimiento'],
            Rol::AUXILIAR => [
                'ver_empresa', 'ver_oficinas', 'ver_empresas_convenio', 'ver_valores_administracion',
                'ver_propietarios', 'crear_propietarios', 'editar_propietarios',
                'ver_vehiculos', 'crear_vehiculos', 'editar_vehiculos',
                'ver_documentos_vehiculo', 'crear_documentos_vehiculo', 'editar_documentos_vehiculo',
                'ver_novedades_vehiculo',
                'ver_conductores', 'crear_conductores', 'editar_conductores',
                'ver_documentos_conductor', 'crear_documentos_conductor', 'editar_documentos_conductor',
                'ver_novedades_conductor',
                'ver_asignaciones_vehiculo', 'crear_asignaciones_vehiculo', 'editar_asignaciones_vehiculo',
                'ver_clientes', 'crear_clientes', 'editar_clientes',
                'ver_contratos', 'crear_contratos', 'editar_contratos',
                'ver_pagos_contrato', 'crear_pagos_contrato',
                'ver_fuecs', 'crear_fuecs',
                'ver_servicios', 'crear_servicios', 'editar_servicios', 'anular_servicios',
                'ver_rutas', 'crear_rutas', 'editar_rutas',
                'ver_plantillas_contrato', 'ver_documentos_contrato', 'crear_documentos_contrato',
                'ver_items_chequeo', 'ver_inspecciones', 'crear_inspecciones', 'crear_portal_conductor',
                'ver_mantenimientos', 'crear_mantenimientos', 'ver_planes_accion', 'ver_rutinas_mantenimiento',
                'ver_programas_mantenimiento',
            ],
            Rol::MANTENIMIENTO => [
                'ver_vehiculos', 'ver_documentos_vehiculo', 'ver_novedades_vehiculo',
                'ver_conductores', 'ver_asignaciones_vehiculo',
                'ver_items_chequeo', 'ver_inspecciones', 'crear_inspecciones',
                'ver_mantenimientos', 'crear_mantenimientos', 'anular_mantenimientos',
                'ver_planes_accion', 'crear_planes_accion', 'editar_planes_accion',
                'ver_rutinas_mantenimiento', 'crear_rutinas_mantenimiento', 'editar_rutinas_mantenimiento', 'eliminar_rutinas_mantenimiento',
                'ver_programas_mantenimiento', 'crear_programas_mantenimiento', 'editar_programas_mantenimiento',
            ],
            Rol::CONDUCTOR => [],
        ];
    }
}
