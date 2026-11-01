<?php

namespace App\Support\Pesv;

use App\Enums\TipoInspeccion;
use App\Models\Empresa;
use App\Models\ItemChequeo;

/**
 * Ítems por defecto de las listas de chequeo. Salen de la app anterior (alistamiento diario de
 * 19 ítems e inspección mensual PESV) agrupados y sin repetidos. Cada empresa los ajusta en
 * Seguridad vial → Ítems de chequeo. Críticos: los que, si fallan, impiden operar el vehículo.
 */
final class ItemsBase
{
    /** [grupo, descripción, crítico] */
    public const PREOPERACIONAL = [
        ['Motor y líquidos', 'Nivel de aceite del motor', false],
        ['Motor y líquidos', 'Nivel de refrigerante', false],
        ['Motor y líquidos', 'Nivel de líquido de frenos', true],
        ['Motor y líquidos', 'Nivel de líquido de dirección', false],
        ['Frenos y dirección', 'Freno de pedal y freno de mano', true],
        ['Frenos y dirección', 'Dirección sin juego anormal', true],
        ['Llantas', 'Llantas y rines en buen estado (sin cortes, labrado suficiente)', true],
        ['Llantas', 'Llanta de repuesto', false],
        ['Luces y señales', 'Luces altas, bajas, de freno y de reversa', true],
        ['Luces y señales', 'Direccionales y luces de parqueo', true],
        ['Luces y señales', 'Pito', false],
        ['Luces y señales', 'Testigos del tablero sin alertas', false],
        ['Visibilidad', 'Vidrios en buen estado', false],
        ['Visibilidad', 'Espejos retrovisores', true],
        ['Visibilidad', 'Limpiaparabrisas', false],
        ['Seguridad', 'Cinturones de seguridad', true],
        ['Seguridad', 'Extintor cargado y vigente', true],
        ['Seguridad', 'Botiquín', false],
        ['Seguridad', 'Kit de carretera (conos, gato, cruceta, tacos)', false],
        ['Carrocería', 'Carrocería y puertas en buen estado', false],
        ['Carrocería', 'Aseo del vehículo', false],
        ['Documentos', 'Licencia de tránsito, SOAT, revisión técnico-mecánica y tarjeta de operación a bordo', true],
    ];

    public const MENSUAL = [
        ['Cabina', 'Aseo y estado de la cabina y la silletería', false],
        ['Cabina', 'Recipiente para basura', false],
        ['Llantas', 'Estado de las llantas', true],
        ['Llantas', 'Presión de inflado', false],
        ['Llantas', 'Llanta de repuesto', false],
        ['Seguridad', 'Extintor: carga, manómetro y fecha de vencimiento', true],
        ['Seguridad', 'Botiquín completo y vigente', false],
        ['Seguridad', 'Equipo de carretera completo', false],
        ['Seguridad', 'Cinturones de seguridad', true],
        ['Luces y señales', 'Luces delanteras y traseras', true],
        ['Luces y señales', 'Direccionales', true],
        ['Luces y señales', 'Pito', false],
        ['Frenos y dirección', 'Dirección', true],
        ['Frenos y dirección', 'Frenos', true],
        ['Motor', 'Sistema de combustible sin fugas', true],
        ['Motor', 'Motor sin fugas ni ruidos anormales', false],
        ['Motor', 'Niveles de líquidos', false],
        ['Visibilidad', 'Limpiaparabrisas', false],
        ['Visibilidad', 'Espejos', true],
        ['Carrocería', 'Condición general de la carrocería', false],
        ['Documentos', 'SOAT vigente', true],
        ['Documentos', 'Revisión técnico-mecánica vigente', true],
        ['Documentos', 'Licencia de conducción vigente', true],
        ['Documentos', 'Tarjeta de operación vigente', true],
        ['Documentos', 'Pólizas de responsabilidad civil vigentes', true],
    ];

    /** Crea los ítems por defecto de los tipos que la empresa aún no tiene. */
    public static function crear(Empresa $empresa): void
    {
        foreach ([TipoInspeccion::Preoperacional->value => self::PREOPERACIONAL, TipoInspeccion::Mensual->value => self::MENSUAL] as $tipo => $items) {
            if (ItemChequeo::query()->deEmpresa($empresa)->where('tipo', $tipo)->exists()) {
                continue;
            }

            foreach ($items as $i => [$grupo, $descripcion, $critico]) {
                $item = new ItemChequeo(['tipo' => $tipo, 'grupo' => $grupo, 'descripcion' => $descripcion, 'critico' => $critico, 'orden' => ($i + 1) * 10]);
                $item->empresa_id = $empresa->id;
                $item->save();
            }
        }
    }
}
