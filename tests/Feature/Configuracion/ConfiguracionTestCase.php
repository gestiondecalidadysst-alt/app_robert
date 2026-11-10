<?php

namespace Tests\Feature\Configuracion;

use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class ConfiguracionTestCase extends TestCase
{
    use RefreshDatabase;

    protected Empresa $demo;

    protected Empresa $otra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogosSeeder::class, PermisosSeeder::class, DemoSeeder::class]);

        $this->demo = Empresa::where('slug', 'cootrasec-demo')->firstOrFail();
        $this->otra = Empresa::where('slug', 'transportes-ejemplo')->firstOrFail();
    }

    protected function usuario(string $email): User
    {
        $usuario = User::query()->withoutGlobalScopes()->where('email', $email)->firstOrFail();

        // El panel admin exige 2FA: el superadministrador de prueba ya lo tiene configurado,
        // para que sus pruebas evalúen la pantalla objetivo y no la configuración del segundo factor.
        if ($usuario->es_superadmin && ! $usuario->getAppAuthenticationSecret()) {
            $usuario->saveAppAuthenticationSecret('TOTPDEPRUEBASUPERADMIN');
        }

        return $usuario;
    }

    /** Simula estar dentro del panel de empresa con la empresa indicada. */
    protected function entrarComo(string $email, ?Empresa $empresa = null): User
    {
        $usuario = $this->usuario($email);
        $empresa ??= $this->demo;

        $this->actingAs($usuario);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($empresa);
        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

        return $usuario;
    }
}
