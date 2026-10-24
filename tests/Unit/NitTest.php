<?php

namespace Tests\Unit;

use App\Support\Nit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NitTest extends TestCase
{
    public static function nitsConocidos(): array
    {
        return [
            'DIAN' => ['800197268', 4],
            'Bancolombia' => ['890903938', 8],
            'Ecopetrol' => ['899999068', 1],
            'con puntos y guion' => ['800.197.268', 4],
        ];
    }

    #[DataProvider('nitsConocidos')]
    public function test_calcula_el_digito_de_verificacion(string $nit, int $esperado): void
    {
        $this->assertSame($esperado, Nit::digitoVerificacion($nit));
    }

    public function test_nit_vacio_no_tiene_digito(): void
    {
        $this->assertNull(Nit::digitoVerificacion(''));
    }

    public function test_formatea_con_puntos_y_digito(): void
    {
        $this->assertSame('800.197.268-4', Nit::formatear('800197268'));
    }
}
