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
        'propietarios' => ['Propietarios', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'vehiculos' => ['Vehículos', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'documentos_vehiculo' => ['Documentos de vehículos', 'Flota', ['ver', 'crear', 'editar', 'eliminar']],
        'novedades_vehiculo' => ['Novedades y estado de vehículos', 'Flota', ['ver', 'crear']],
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
        $soloVer = array_values(array_filter($todos, fn (string $p) => str_starts_with($p, 'ver_')));

        return [
            Rol::ADMINISTRADOR => $todos,
            Rol::GERENCIA => $soloVer,
            Rol::AUXILIAR => [
                'ver_empresa', 'ver_oficinas', 'ver_empresas_convenio', 'ver_valores_administracion',
                'ver_propietarios', 'crear_propietarios', 'editar_propietarios',
                'ver_vehiculos', 'crear_vehiculos', 'editar_vehiculos',
                'ver_documentos_vehiculo', 'crear_documentos_vehiculo', 'editar_documentos_vehiculo',
                'ver_novedades_vehiculo',
            ],
            Rol::MANTENIMIENTO => ['ver_vehiculos', 'ver_documentos_vehiculo', 'ver_novedades_vehiculo'],
            Rol::CONDUCTOR => [],
        ];
    }
}
