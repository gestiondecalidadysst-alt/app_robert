<?php

namespace App\Support\Pdf;

/**
 * Secciones que el usuario puede elegir para la hoja de vida de un vehículo o un conductor.
 *
 * Cada constante es una clave estable (se guarda en la selección del formulario) con su
 * etiqueta. El generador arma solo las secciones elegidas, en este orden.
 */
final class SeccionHojaVida
{
    /** Secciones de la hoja de vida de un vehículo. @var array<string, string> */
    public const VEHICULO = [
        'datos' => 'Datos generales',
        'documentos' => 'Documentos y vencimientos',
        'conductores' => 'Conductores que lo han conducido',
        'servicios' => 'Servicios prestados',
        'preoperacionales' => 'Preoperacionales e inspecciones',
        'mantenimientos' => 'Mantenimientos',
        'novedades' => 'Novedades',
        'planes' => 'Planes de acción',
    ];

    /** Secciones de la hoja de vida de un conductor. @var array<string, string> */
    public const CONDUCTOR = [
        'datos' => 'Datos generales',
        'documentos' => 'Documentos y licencia',
        'vehiculos' => 'Vehículos que ha conducido',
        'servicios' => 'Servicios prestados',
        'preoperacionales' => 'Preoperacionales firmados',
        'novedades' => 'Novedades',
    ];

    /** Secciones que dependen de un rango de fechas (las demás son el estado actual). */
    public const CON_RANGO = ['servicios', 'preoperacionales', 'mantenimientos', 'novedades'];
}
