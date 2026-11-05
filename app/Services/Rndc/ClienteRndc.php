<?php

namespace App\Services\Rndc;

use App\Models\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del web service del RNDC. Arma el "Request" (<root> con <acceso>, <solicitud> y
 * <variables>), lo envía por SOAP al método AtenderMensajeRNDC y devuelve la respuesta interpretada.
 */
final class ClienteRndc
{
    public function __construct(private readonly Empresa $empresa) {}

    /** XML del Request. Con $ocultarClave se usa para guardar en la bitácora y mostrar. */
    public static function request(string $usuario, string $clave, int $tipo, int $proceso, array $variables, array $documento = []): string
    {
        $x = "<?xml version='1.0' encoding='ISO-8859-1' ?>\n<root>\n<acceso>\n"
            .'<username>'.self::e($usuario)."</username>\n<password>".self::e($clave)."</password>\n</acceso>\n"
            ."<solicitud>\n<tipo>{$tipo}</tipo>\n<procesoid>{$proceso}</procesoid>\n</solicitud>\n<variables>\n";
        foreach ($variables as $k => $v) {
            // Grupo anidado (REMESASMAN, PREREMESAS): ['@procesoid' => 43, 'items' => [['tag' => 'REMESA', 'vars' => [...]], ...]]
            if (is_array($v) && isset($v['items'])) {
                $attr = isset($v['@procesoid']) ? ' procesoid="'.(int) $v['@procesoid'].'"' : '';
                $x .= "<{$k}{$attr}>\n";
                foreach ($v['items'] as $item) {
                    $x .= "<{$item['tag']}>\n";
                    foreach ($item['vars'] as $ik => $iv) {
                        $x .= "<{$ik}>".self::e((string) $iv)."</{$ik}>\n";
                    }
                    $x .= "</{$item['tag']}>\n";
                }
                $x .= "</{$k}>\n";
            } else {
                $x .= "<{$k}>".self::e((string) $v)."</{$k}>\n";
            }
        }
        $x .= "</variables>\n";
        if ($documento) {
            $x .= "<documento>\n";
            foreach ($documento as $k => $v) {
                $x .= "<{$k}>".self::e((string) $v)."</{$k}>\n";
            }
            $x .= "</documento>\n";
        }

        return $x.'</root>';
    }

    public function enviar(int $tipo, int $proceso, array $variables, array $documento = []): RespuestaRndc
    {
        $request = self::request((string) $this->empresa->rndc_usuario, (string) $this->empresa->rndc_clave, $tipo, $proceso, $variables, $documento);
        $ns = config('rndc.namespace');
        $sobre = '<?xml version="1.0" encoding="ISO-8859-1"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="'.$ns.'">'
            .'<soapenv:Header/><soapenv:Body><urn:AtenderMensajeRNDC><Request>'.htmlspecialchars($request, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</Request></urn:AtenderMensajeRNDC></soapenv:Body></soapenv:Envelope>';
        $url = $this->empresa->rndc_ambiente === 'produccion' ? config('rndc.url') : config('rndc.url_pruebas');
        $inicio = microtime(true);

        try {
            $respuesta = Http::withHeaders(['SOAPAction' => $ns.'#AtenderMensajeRNDC', 'Content-Type' => 'text/xml; charset=ISO-8859-1'])
                ->timeout(config('rndc.timeout'))->connectTimeout(15)
                ->withBody(mb_convert_encoding($sobre, 'ISO-8859-1', 'UTF-8'), 'text/xml; charset=ISO-8859-1')
                ->post($url);
        } catch (ConnectionException $e) {
            return new RespuestaRndc(null, [['codigo' => null, 'mensaje' => 'No hubo conexión con el RNDC: '.$e->getMessage()]], '', self::ms($inicio));
        }

        $cuerpo = mb_convert_encoding((string) $respuesta->body(), 'UTF-8', 'ISO-8859-1, UTF-8');
        if (! $respuesta->successful()) {
            return new RespuestaRndc(null, [['codigo' => null, 'mensaje' => "El RNDC respondió {$respuesta->status()}."]], $cuerpo, self::ms($inicio));
        }

        // La respuesta SOAP trae el XML del RNDC dentro de <return> (escapado).
        $interno = preg_match('/<return[^>]*>(.*?)<\/return>/is', $cuerpo, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : $cuerpo;

        return RespuestaRndc::desdeXml($interno, self::ms($inicio));
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function ms(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
