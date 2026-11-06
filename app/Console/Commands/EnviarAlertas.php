<?php

namespace App\Console\Commands;

use App\Mail\CorreoAlertas;
use App\Models\DocumentoConductor;
use App\Models\DocumentoVehiculo;
use App\Models\Empresa;
use App\Services\Mensajeria\Mensajero;
use App\Support\Alertas\CentroAlertas;
use App\Support\Avisos;
use App\Support\Push\NotificarConductor;
use App\Support\Push\WebPush;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Avisos del centro de alertas. Dos modos:
 * - semanal (lunes): resumen de todo lo por vencer/vencido al propietario y al conductor, y
 *   campana al equipo con permiso.
 * - diario: aviso puntual de lo que vence HOY (se dispara una sola vez, el día del vencimiento).
 *
 * El correo al propietario/conductor solo sale si tienen correo y algo pendiente. Va por la cola.
 */
class EnviarAlertas extends Command
{
    protected $signature = 'ruta:alertas {--modo=semanal : semanal (resumen) o diario (lo que vence hoy)}';

    protected $description = 'Envía los avisos de documentos por vencer/vencidos y administración en mora.';

    public function handle(): int
    {
        $modo = $this->option('modo') === 'diario' ? 'diario' : 'semanal';

        Empresa::query()->activas()->each(fn (Empresa $empresa) => $modo === 'diario'
            ? $this->diario($empresa)
            : $this->semanal($empresa));

        return self::SUCCESS;
    }

    private function semanal(Empresa $empresa): void
    {
        $docsVehiculo = CentroAlertas::documentosVehiculo($empresa)->with('vehiculo.propietario')->get();
        $mora = CentroAlertas::administracionActiva($empresa)
            ? CentroAlertas::cuotasEnMora($empresa)->with('vehiculo.propietario')->get()
            : collect();
        $docsConductor = CentroAlertas::documentosConductor($empresa)->with('conductor')->get();

        $this->avisarPropietarios($empresa, $docsVehiculo, $mora, 'Documentos y pagos por atender',
            'Estos son los pendientes de sus vehículos:');
        $this->avisarConductores($empresa, $docsConductor, 'Documentos por renovar',
            'Estos documentos suyos están por vencer o vencidos:');

        $this->campana($empresa, 'Alertas de la semana');
    }

    private function diario(Empresa $empresa): void
    {
        $docsVehiculo = DocumentoVehiculo::query()->deEmpresa($empresa)->vigentes()
            ->whereDate('fecha_vencimiento', today())
            ->whereHas('vehiculo', fn ($q) => $q->enFlota())
            ->with('vehiculo.propietario')->get();

        $docsConductor = DocumentoConductor::query()->deEmpresa($empresa)->vigentes()
            ->whereDate('fecha_vencimiento', today())
            ->whereHas('conductor', fn ($q) => $q->vinculados())
            ->with('conductor')->get();

        if ($docsVehiculo->isEmpty() && $docsConductor->isEmpty()) {
            return;
        }

        $this->avisarPropietarios($empresa, $docsVehiculo, collect(), 'Un documento de su vehículo vence hoy',
            'Hoy vence el siguiente documento:');
        $this->avisarConductores($empresa, $docsConductor, 'Un documento suyo vence hoy',
            'Hoy vence el siguiente documento:');

        // Recordatorio push del preoperacional a los conductores con servicio hoy.
        NotificarConductor::preoperacionalPendiente($empresa);

        $this->campana($empresa, 'Vence hoy', soloHoy: true);
    }

    /** Un correo por propietario con los documentos de sus vehículos y la administración en mora. */
    private function avisarPropietarios(Empresa $empresa, Collection $docs, Collection $mora, string $titulo, string $intro): void
    {
        $items = [];

        foreach ($docs as $doc) {
            $propietario = $doc->vehiculo?->propietario;
            if (! $propietario) {
                continue;
            }
            $items[$propietario->id]['propietario'] = $propietario;
            $items[$propietario->id]['lista'][] = $this->itemDocumento(
                $doc->tipo?->getLabel() ?? 'Documento',
                'vehículo '.($doc->vehiculo?->placa ?? ''),
                $doc->fecha_vencimiento,
                $doc->diasParaVencer(),
            );
        }

        foreach ($mora as $cuota) {
            $propietario = $cuota->vehiculo?->propietario;
            if (! $propietario) {
                continue;
            }
            $items[$propietario->id]['propietario'] = $propietario;
            $items[$propietario->id]['lista'][] = [
                'texto' => 'Administración en mora · vehículo '.($cuota->vehiculo?->placa ?? ''),
                'detalle' => $cuota->periodoTexto().': pendiente $ '.number_format($cuota->saldo(), 0, ',', '.'),
                'urgente' => true,
            ];
        }

        foreach ($items as $grupo) {
            $this->enviar($empresa, $grupo['propietario']->email, $grupo['propietario']->nombre_completo, $titulo, $intro, $grupo['lista']);
        }
    }

    /** Un correo por conductor con sus documentos. */
    private function avisarConductores(Empresa $empresa, Collection $docs, string $titulo, string $intro): void
    {
        $items = [];

        foreach ($docs as $doc) {
            $conductor = $doc->conductor;
            if (! $conductor) {
                continue;
            }
            $items[$conductor->id]['conductor'] = $conductor;
            $items[$conductor->id]['lista'][] = $this->itemDocumento(
                $doc->tipo?->getLabel() ?? 'Documento',
                'suyo',
                $doc->fecha_vencimiento,
                $doc->diasParaVencer(),
            );
        }

        foreach ($items as $grupo) {
            $conductor = $grupo['conductor'];
            $this->enviar($empresa, $conductor->email, $conductor->nombre_completo, $titulo, $intro, $grupo['lista']);

            // Además del correo, notificación push al celular del conductor (si tiene la PWA y avisos activos).
            if ($conductor->usuario) {
                $n = count($grupo['lista']);
                WebPush::aUsuario($conductor->usuario, $titulo, $n === 1
                    ? $grupo['lista'][0]['texto'].' — '.$grupo['lista'][0]['detalle']
                    : "Tienes {$n} documentos por renovar. Ábrelos en la app.", route('conductor.inicio'), 'vencimientos');
            }
        }
    }

    /** @return array{texto:string, detalle:string, urgente:bool} */
    private function itemDocumento(string $tipo, string $ref, \DateTimeInterface $vence, int $dias): array
    {
        $fecha = $vence->format('d/m/Y');
        $detalle = $dias < 0
            ? "Venció el {$fecha} (hace ".abs($dias).' día(s))'
            : ($dias === 0 ? "Vence hoy {$fecha}" : "Vence el {$fecha} (en {$dias} día(s))");

        return ['texto' => trim("{$tipo} · {$ref}"), 'detalle' => $detalle, 'urgente' => $dias <= 0];
    }

    private function enviar(Empresa $empresa, ?string $correo, string $destinatario, string $titulo, string $intro, array $items): void
    {
        if (blank($correo) || $items === []) {
            return;
        }

        Mensajero::correo($empresa, 'alertas', $correo, new CorreoAlertas(
            $empresa->nombre_comercial ?: $empresa->razon_social,
            $destinatario,
            $titulo,
            $intro,
            $items,
        ));
    }

    /** Campana al equipo con permiso, con el resumen de lo que requiere atención. */
    private function campana(Empresa $empresa, string $titulo, bool $soloHoy = false): void
    {
        if ($soloHoy) {
            $vehiculo = DocumentoVehiculo::query()->deEmpresa($empresa)->vigentes()
                ->whereDate('fecha_vencimiento', today())->whereHas('vehiculo', fn ($q) => $q->enFlota())->count();
            $conductor = DocumentoConductor::query()->deEmpresa($empresa)->vigentes()
                ->whereDate('fecha_vencimiento', today())->whereHas('conductor', fn ($q) => $q->vinculados())->count();
            $mora = 0;
        } else {
            $vehiculo = CentroAlertas::documentosVehiculo($empresa)->count();
            $conductor = CentroAlertas::documentosConductor($empresa)->count();
            $mora = CentroAlertas::administracionActiva($empresa)
                ? CentroAlertas::cuotasEnMora($empresa)->distinct('vehiculo_id')->count('vehiculo_id') : 0;
        }

        if ($vehiculo + $conductor + $mora === 0) {
            return;
        }

        $partes = array_filter([
            $vehiculo ? "{$vehiculo} de vehículos" : null,
            $conductor ? "{$conductor} de conductores" : null,
            $mora ? "{$mora} vehículo(s) con administración en mora" : null,
        ]);

        $notificacion = Notification::make()
            ->title($titulo)
            ->body('Documentos: '.implode(' · ', $partes).'. Revise la pantalla de Vencimientos.')
            ->icon('heroicon-o-bell-alert')
            ->color($soloHoy || $mora ? 'danger' : 'warning');

        Avisos::aQuienPueda($empresa, 'ver_documentos_vehiculo', $notificacion);
    }
}
