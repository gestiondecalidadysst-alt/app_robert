<?php

namespace Tests\Feature\Configuracion;

use App\Models\Rol;

class AccesoPantallasTest extends ConfiguracionTestCase
{
    private function pantallasEmpresa(string $slug): array
    {
        return [
            "/app/{$slug}",
            "/app/{$slug}/profile",
            "/app/{$slug}/oficinas",
            "/app/{$slug}/usuarios",
            "/app/{$slug}/usuarios/crear",
            "/app/{$slug}/roles",
            "/app/{$slug}/roles/crear",
            "/app/{$slug}/valores-administracion",
            "/app/{$slug}/empresas-convenio",
            "/app/{$slug}/auditoria",
        ];
    }

    public function test_el_administrador_abre_todas_las_pantallas_de_su_empresa(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        foreach ($this->pantallasEmpresa('cootrasec-demo') as $url) {
            $this->get($url)->assertOk();
        }

        $rol = Rol::query()->deEmpresa($this->demo)->first();
        $this->get("/app/cootrasec-demo/roles/{$rol->id}/editar")->assertOk();

        $usuario = $this->usuario('auxiliar@demo.test');
        $this->get("/app/cootrasec-demo/usuarios/{$usuario->id}/editar")->assertOk();
    }

    public function test_el_superadministrador_abre_el_panel_de_plataforma(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));

        foreach ([
            '/admin', '/admin/empresas', '/admin/empresas/crear', '/admin/usuarios',
            '/admin/departamentos', '/admin/municipios', '/admin/marcas', '/admin/lineas',
            '/admin/clases-vehiculo', '/admin/carrocerias', '/admin/combustibles', '/admin/tipos-servicio',
            '/admin/aseguradoras', '/admin/tipos-identificacion', '/admin/categorias-licencia',
            '/admin/eps', '/admin/arl', '/admin/niveles-academicos',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/admin/empresas/{$this->demo->id}/editar")->assertOk();
    }

    public function test_un_usuario_de_empresa_no_entra_al_panel_de_plataforma(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $this->get('/admin')->assertForbidden();
    }

    public function test_gerencia_ve_pero_no_crea_ni_edita(): void
    {
        $this->actingAs($this->usuario('gerencia@demo.test'));

        $this->get('/app/cootrasec-demo/usuarios')->assertOk();
        $this->get('/app/cootrasec-demo/usuarios/crear')->assertForbidden();
        $this->get('/app/cootrasec-demo/roles/crear')->assertForbidden();
    }

    public function test_auxiliar_no_ve_usuarios_ni_roles(): void
    {
        $this->actingAs($this->usuario('auxiliar@demo.test'));

        $this->get('/app/cootrasec-demo/oficinas')->assertOk();
        $this->get('/app/cootrasec-demo/usuarios')->assertForbidden();
        $this->get('/app/cootrasec-demo/roles')->assertForbidden();
        $this->get('/app/cootrasec-demo/auditoria')->assertForbidden();
    }

    public function test_no_se_puede_entrar_a_otra_empresa(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $this->get('/app/transportes-ejemplo')->assertNotFound();
        $this->get('/app/transportes-ejemplo/usuarios')->assertNotFound();
    }

    public function test_no_se_ven_registros_de_otra_empresa_por_url(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $usuarioAjeno = $this->usuario('admin@ejemplo.test');
        $this->get("/app/cootrasec-demo/usuarios/{$usuarioAjeno->id}/editar")->assertNotFound();

        $rolAjeno = Rol::query()->deEmpresa($this->otra)->first();
        $this->get("/app/cootrasec-demo/roles/{$rolAjeno->id}/editar")->assertNotFound();
    }

    public function test_empresa_inactiva_bloquea_el_acceso(): void
    {
        $this->otra->update(['activa' => false]);
        $this->actingAs($this->usuario('admin@ejemplo.test'));

        $this->get('/app')->assertForbidden();
    }
}
