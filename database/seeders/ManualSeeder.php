<?php

namespace Database\Seeders;

use App\Models\SeccionManual;
use Illuminate\Database\Seeder;

/**
 * Contenido inicial del manual de la aplicación. Usa firstOrCreate por slug: siembra lo que
 * falte sin pisar lo que el superadministrador haya editado desde el panel.
 */
class ManualSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->secciones() as $seccion) {
            SeccionManual::query()->firstOrCreate(['slug' => $seccion['slug']], $seccion);
        }
    }

    /** @return list<array<string, mixed>> */
    private function secciones(): array
    {
        return [
            [
                'slug' => 'vision-general',
                'grupo' => 'Introducción',
                'orden' => 1,
                'icono' => 'heroicon-o-map',
                'titulo' => 'Visión general de la plataforma',
                'contenido' => <<<'HTML'
                <p>Esta plataforma administra empresas de transporte especial y de carga. Cada empresa trabaja en su propio espacio (sus vehículos, conductores, contratos y documentos no se mezclan con los de otra). La plataforma se opera desde dos lugares:</p>
                <ul>
                    <li><strong>Panel de la empresa</strong> (<em>/app</em>): donde cada empresa gestiona su operación diaria.</li>
                    <li><strong>Panel de plataforma</strong> (<em>/admin</em>): donde el superadministrador crea empresas, define catálogos nacionales, cobra los servicios compartidos (SMS, consultas de tránsito) y vigila el estado del sistema.</li>
                </ul>
                <h3>Cómo está organizada</h3>
                <p>El trabajo se agrupa en módulos: <strong>Configuración</strong>, <strong>Flota</strong>, <strong>Conductores</strong>, <strong>Operación</strong> (contratos y FUEC), <strong>Contratos y firma</strong>, <strong>PESV y portal del conductor</strong>, <strong>Mantenimiento</strong>, <strong>Gerencia</strong>, <strong>Carga (RNDC)</strong>, <strong>Consultas de tránsito</strong>, <strong>Pagos de administración</strong> y los servicios de <strong>plataforma</strong>. Cada empresa ve solo los módulos que tiene contratados.</p>
                <blockquote>Este manual se edita desde el mismo panel: cualquier sección se puede actualizar con el botón «Editar» y se puede descargar todo en PDF para capacitar al personal.</blockquote>
                HTML,
            ],
            [
                'slug' => 'operacion-clientes-contratos-fuec',
                'grupo' => 'Operación',
                'orden' => 1,
                'icono' => 'heroicon-o-document-text',
                'titulo' => 'Operación: clientes, contratos y FUEC',
                'contenido' => <<<'HTML'
                <p>Es el corazón del negocio de transporte especial: desde que llega un cliente hasta que el vehículo sale a la vía con su permiso en regla (el FUEC).</p>

                <h3>Qué hace, paso a paso en la vida real</h3>
                <ol>
                    <li>Llega un cliente (un colegio, una empresa, un grupo de turismo) y se registra una sola vez.</li>
                    <li>Se le hace un <strong>contrato</strong> con la vigencia y el objeto del servicio.</li>
                    <li>Por cada viaje se expide un <strong>FUEC</strong> (el extracto obligatorio), que asigna vehículo y conductor.</li>
                    <li>A medida que el cliente paga, se registran los <strong>pagos</strong> y se le entrega un <strong>recibo de caja</strong>.</li>
                </ol>

                <h3>Componentes y qué hace cada uno</h3>
                <p><strong>Clientes.</strong> La lista de a quién se le presta el servicio. Guarda identificación y contacto. En la vida real: se crea una vez y se reutiliza en todos sus contratos.</p>
                <p><strong>Contratos.</strong> El acuerdo con el cliente (vigencia, objeto, valor). De aquí nacen los FUEC. Puede llevar <strong>pagos con saldo</strong>: la plataforma sabe cuánto ha abonado y cuánto debe. Ejemplo: un colegio firma un contrato por el año; cada mes abona y el sistema baja el saldo.</p>
                <p><strong>Expedición del FUEC.</strong> El permiso por viaje, con el formato oficial (Resolución 6652/2019), su número consecutivo, el vehículo, el o los conductores y la ruta. Se genera en <strong>PDF con un código QR</strong> que cualquier autoridad puede verificar en línea. En la vida real: el conductor lleva el FUEC (impreso o en el celular) y, si lo detienen, el inspector escanea el QR y confirma que es auténtico.</p>
                <p><strong>Recibos de caja.</strong> El comprobante de cada abono o pago total, con consecutivo propio de la empresa y QR de verificación. Cuando el saldo llega a cero, el <strong>estado de cuenta</strong> sirve de paz y salvo.</p>
                <p><strong>Cotizador.</strong> Permite cotizarle a un cliente o prospecto (rutas, duración, valor, descuento e IVA) con un PDF con la marca de la empresa; si la acepta, la cotización se convierte en contrato.</p>
                <p><strong>Planeador de servicios.</strong> Un tablero semanal por vehículo o conductor para organizar los viajes; de cada servicio se genera el extracto y, opcionalmente, un plan de viaje.</p>

                <h3>Reglas importantes</h3>
                <ul>
                    <li><strong>Sin cruce de horarios:</strong> un vehículo o un conductor no se puede asignar a dos FUEC cuyas franjas de horario se crucen. Si un bus presta servicio de 00:00 a 23:59, no se puede escoger para otro ese mismo día.</li>
                    <li><strong>Anulación, no borrado:</strong> un FUEC o un pago no se elimina, se <strong>anula con motivo</strong>, para no romper los consecutivos ni perder la trazabilidad.</li>
                    <li><strong>Firma del contrato (opcional):</strong> si la empresa activa la regla, el contrato debe estar firmado por el cliente antes de expedir el FUEC.</li>
                </ul>

                <h3>Quién lo usa</h3>
                <p>El <strong>Administrador</strong> y el <strong>Auxiliar</strong> de la empresa. Requiere los permisos de clientes, contratos, FUEC y cotizaciones.</p>

                <h3>Qué pasa «detrás»</h3>
                <ul>
                    <li>Cada FUEC y cada recibo generan un <strong>PDF</strong> con <strong>QR de verificación pública</strong> (una página que confirma que el documento es real sin necesidad de entrar al sistema).</li>
                    <li>Los <strong>consecutivos</strong> son propios de cada empresa (su prefijo y número inicial).</li>
                    <li>Los correos y SMS (por ejemplo, el recibo al cliente) salen por una <strong>cola</strong> para no demorar la pantalla.</li>
                </ul>
                HTML,
            ],
        ];
    }
}
