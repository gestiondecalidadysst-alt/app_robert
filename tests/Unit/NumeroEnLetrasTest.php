<?php

namespace Tests\Unit;

use App\Support\Contacto;
use App\Support\NumeroEnLetras;
use PHPUnit\Framework\TestCase;

class NumeroEnLetrasTest extends TestCase
{
    public function test_valores_en_letras(): void
    {
        $casos = [
            0 => 'CERO PESOS M/CTE',
            1 => 'UN PESO M/CTE',
            21 => 'VEINTIÚN PESOS M/CTE',
            31 => 'TREINTA Y UN PESOS M/CTE',
            100 => 'CIEN PESOS M/CTE',
            101 => 'CIENTO UN PESOS M/CTE',
            1000 => 'MIL PESOS M/CTE',
            21000 => 'VEINTIÚN MIL PESOS M/CTE',
            100000 => 'CIEN MIL PESOS M/CTE',
            1000000 => 'UN MILLÓN DE PESOS M/CTE',
            2100000 => 'DOS MILLONES CIEN MIL PESOS M/CTE',
            4500000 => 'CUATRO MILLONES QUINIENTOS MIL PESOS M/CTE',
            18000000 => 'DIECIOCHO MILLONES DE PESOS M/CTE',
            21500000 => 'VEINTIÚN MILLONES QUINIENTOS MIL PESOS M/CTE',
            1000000000 => 'MIL MILLONES DE PESOS M/CTE',
        ];

        foreach ($casos as $valor => $esperado) {
            $this->assertSame($esperado, NumeroEnLetras::pesos($valor), "Valor {$valor}");
        }
    }

    public function test_celulares_y_textos_sms(): void
    {
        $this->assertSame('3001234567', Contacto::celular('+57 300 123 4567'));
        $this->assertSame('3001234567', Contacto::celular('300-123-4567'));
        $this->assertNull(Contacto::celular('6047000000'));
        $this->assertNull(Contacto::celular('30012345'));
        $this->assertSame('••• ••• 4567', Contacto::ocultarCelular('3001234567'));
        $this->assertSame('re•••@colegio.edu.co', Contacto::ocultarCorreo('rectoria@colegio.edu.co'));
        $this->assertSame('Codigo para firmar: ano nino', Contacto::textoSms('Código para firmar: año niño'));
    }
}
