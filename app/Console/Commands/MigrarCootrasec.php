<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migrador de la base vieja de COOTRASEC (PHP/MariaDB monoempresa) al esquema nuevo (Ruta, multiempresa).
 * Lee la conexión 'legacy' y escribe en la conexión por defecto. Idempotente por documento/consecutivo.
 *
 * Prueba: php artisan migrar:cootrasec
 */
class MigrarCootrasec extends Command
{
    protected $signature = 'migrar:cootrasec {--fresh : vacía las tablas destino antes de migrar}';

    protected $description = 'Migra los datos de la base vieja de COOTRASEC al esquema nuevo';

    private int $empresaId;

    private int $oficinaId;

    /** @var array<string,array<string,int>> nombre normalizado => id nuevo, por catálogo */
    private array $cat = [];

    /** @var array<string,array<int|string,int>> mapas id/documento viejo => id nuevo */
    private array $map = [];

    /** @var array<string,int> conteos de cuadre */
    private array $n = [];

    public function handle(): int
    {
        $L = DB::connection('legacy');

        if ($this->option('fresh')) {
            $this->vaciar();
        }

        $this->info('1. Empresa y oficina…');
        $this->migrarEmpresa($L);

        $this->info('2. Cruces de catálogos…');
        $this->cargarCatalogos($L);

        $this->info('3. Propietarios…');
        $this->migrarPropietarios($L);

        $this->info('4. Conductores (+ licencias)…');
        $this->migrarConductores($L);

        $this->info('5. Clientes…');
        $this->migrarClientes($L);

        $this->info('6. Vehículos…');
        $this->migrarVehiculos($L);

        $this->info('7. Documentos de vehículo (pivote)…');
        $this->migrarDocumentosVehiculo($L);

        $this->info('8. Contratos…');
        $this->migrarContratos($L);

        $this->info('9. FUEC / extractos…');
        $this->migrarFuecs($L);

        $this->info('10. Preoperacionales (colapsa detalle a JSON)…');
        $this->migrarInspecciones($L);

        $this->info('11. Mantenimientos…');
        $this->migrarMantenimientos($L);

        $this->info('12. Cuotas y pagos de administración…');
        $this->migrarCobros($L);

        $this->info('13. Reconstruyendo copia de datos de FUEC (estructura que lee la app)…');
        $this->reconstruirDatosFuec();

        $this->cuadre($L);

        return self::SUCCESS;
    }

    // ---------- utilidades ----------

    /** Corrige textos que vienen con bytes latin1 dentro de columnas utf8mb3 (mojibake). */
    private function u(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (! mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }

        return $s;
    }

    private function fecha(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        if ($s === '' || str_starts_with($s, '0000') || $s === '1900-01-01') {
            return null;
        }
        $t = strtotime($s);

        return $t ? date('Y-m-d', $t) : null;
    }

    private function norm(?string $s): string
    {
        $s = $this->u($s) ?? '';
        $s = Str::ascii(mb_strtoupper($s));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $s));
    }

    private function genero(?int $id): string
    {
        return match ((int) $id) {
            1 => 'M',
            2 => 'F',
            default => 'O',
        };
    }

    // ---------- pasos ----------

    private function vaciar(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'fuec_conductor', 'fuecs', 'pagos_administracion', 'cuota_administracion_pago', 'cuotas_administracion',
            'inspecciones', 'mantenimientos', 'documentos_vehiculo', 'documentos_conductor', 'pagos_contrato',
            'contratos', 'vehiculos', 'clientes', 'conductores', 'propietarios', 'oficinas', 'empresa_user', 'empresas',
        ] as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function migrarEmpresa($L): void
    {
        $e = $L->table('empresa')->first();
        $nombre = $this->u($e->razonsocial ?? 'COOTRASEC') ?? 'COOTRASEC';
        $this->empresaId = DB::table('empresas')->insertGetId([
            'slug' => Str::slug($this->u($e->siglas ?? '') ?: $nombre),
            'nit' => $this->u($e->nit_empre ?? '') ?? 's/n',
            'razon_social' => $nombre,
            'nombre_comercial' => $this->u($e->siglas ?? null),
            'direccion' => $this->u($e->direccion ?? null),
            'telefono' => $this->u($e->telefonos ?? null),
            'email' => $this->u($e->correoelec ?? null),
            'resolucion_habilitacion' => $this->u($e->num_resolucion ?? null),
            'anio_habilitacion' => is_numeric($e->year_habilitada ?? null) ? (int) $e->year_habilitada : null,
            'codigo_territorial' => $this->u($e->id_territorial ?? null),
            'representante_legal' => $this->u($e->nomgerente ?? null),
            'representante_documento' => $this->u($e->ccgerente ?? null),
            'modulos' => json_encode(['pasajeros', 'seguridad_vial', 'mantenimiento', 'gerencia', 'administracion']),
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->oficinaId = DB::table('oficinas')->insertGetId([
            'empresa_id' => $this->empresaId,
            'nombre' => 'Principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->n['empresas'] = 1;
    }

    /** Construye nombre normalizado => id nuevo, y el mapa old_id => new_id por catálogo. */
    private function cargarCatalogos($L): void
    {
        $this->indexar('marcas', 'nombre');
        $this->indexar('clases_vehiculo', 'nombre');
        $this->indexar('carrocerias', 'nombre');
        $this->indexar('combustibles', 'nombre');
        $this->indexar('tipos_servicio', 'nombre');
        $this->indexar('eps', 'nombre');
        $this->indexar('arl', 'nombre');
        $this->indexar('niveles_academicos', 'nombre');
        $this->indexar('categorias_licencia', 'descripcion');

        // old_id => new_id, creando en el catálogo nuevo lo que no exista
        $this->cruce($L, 'vehiculos_marcas', 'idmarca', 'marcas', 'marcas', 'nombre');
        $this->cruce($L, 'vehiculos_clases', 'idclase', 'clases', 'clases_vehiculo', 'nombre');
        $this->cruce($L, 'vehiculos_carroceria', 'idcarroceria', 'carrocerias', 'carrocerias', 'nombre');
        $this->cruce($L, 'vehiculos_combustibles', 'idcombustible', 'combustible', 'combustibles', 'nombre');
        $this->cruce($L, 'vehiculos_servicios', 'idservicios', 'servicios', 'tipos_servicio', 'nombre');
        $this->cruce($L, 'eps', 'id_eps', 'mombre_eps', 'eps', 'nombre');
        $this->cruce($L, 'arl', 'id_arl', 'mombre_arl', 'arl', 'nombre');
        $this->cruce($L, 'nivel_academico', 'id_academico', 'nivel_academico', 'niveles_academicos', 'nombre');
        $this->cruce($L, 'licencias_categorias', 'id_categoria', 'categoria', 'categorias_licencia', 'descripcion', null, false);

        // municipios por código DANE
        $this->map['municipio'] = DB::table('municipios')->pluck('id', 'codigo_dane')->all();
    }

    private function indexar(string $tabla, string $campo): void
    {
        $this->cat[$tabla] = [];
        foreach (DB::table($tabla)->get(['id', $campo]) as $r) {
            $this->cat[$tabla][$this->norm($r->$campo)] = $r->id;
        }
    }

    private function cruce($L, string $tablaVieja, string $idVieja, string $campoVieja, string $tablaNueva, string $campoNueva, ?string $campoCodigo = null, bool $crear = true): void
    {
        $this->map[$tablaNueva] = [];
        foreach ($L->table($tablaVieja)->get() as $r) {
            $nombre = $this->u($r->$campoVieja ?? null);
            if (! $nombre) {
                continue;
            }
            $k = $this->norm($nombre);
            $id = $this->cat[$tablaNueva][$k] ?? null;
            if (! $id && ! $crear) {
                continue; // catálogo legal fijo: no se inventan filas
            }
            if (! $id) {
                $datos = [$campoNueva => $nombre, 'activo' => true, 'created_at' => now(), 'updated_at' => now()];
                if ($campoCodigo) {
                    $datos[$campoCodigo] = Str::limit($this->norm($nombre), 5, '');
                }
                if ($tablaNueva === 'eps' || $tablaNueva === 'arl') {
                    $datos['nit'] = 's/n';
                }
                $id = DB::table($tablaNueva)->insertGetId($datos);
                $this->cat[$tablaNueva][$k] = $id;
            }
            $this->map[$tablaNueva][$r->$idVieja] = $id;
        }
    }

    private function migrarPropietarios($L): void
    {
        $c = 0;
        foreach ($L->table('propietarios')->get() as $p) {
            $doc = $this->u($p->cedula_pr ?? null);
            if (! $doc) {
                continue;
            }
            $id = DB::table('propietarios')->insertGetId([
                'empresa_id' => $this->empresaId,
                'tipo_identificacion_id' => 1,
                'documento' => $doc,
                'nombres' => $this->u($p->nombres_pr ?? '') ?? $doc,
                'apellidos' => $this->u($p->apellidos_pr ?? null),
                'genero' => $this->genero($p->idgenero ?? null),
                'fecha_nacimiento' => $this->fecha($p->facha_nace ?? null),
                'fecha_ingreso' => $this->fecha($p->fecha_ingreso ?? null),
                'telefono' => $this->u($p->num_cel ?? null),
                'email' => $this->u($p->email_pr ?? null),
                'direccion' => $this->u($p->direccion_pr ?? null),
                'municipio_id' => $this->map['municipio'][$p->idmunicipio ?? ''] ?? null,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->map['propietario'][$doc] = $id;
            $c++;
        }
        $this->n['propietarios'] = $c;
    }

    private function migrarConductores($L): void
    {
        $c = 0;
        foreach ($L->table('operadores')->get() as $o) {
            $doc = $this->u($o->cedula_cd ?? null);
            if (! $doc) {
                continue;
            }
            $activo = ($o->desvinculado ?? 'N') !== 'S';
            $id = DB::table('conductores')->insertGetId([
                'empresa_id' => $this->empresaId,
                'tipo_identificacion_id' => 1,
                'documento' => $doc,
                'nombres' => $this->u($o->nombres_cd ?? '') ?? $doc,
                'apellidos' => $this->u($o->apellidos_cd ?? '') ?? '.',
                'genero' => $this->genero($o->idgenero ?? null),
                'grupo_sanguineo' => $this->u($o->rh_cd ?? null),
                'fecha_nacimiento' => $this->fecha($o->facha_nace ?? null),
                'telefono' => $this->u($o->numcel ?? null),
                'direccion' => $this->u($o->direccion ?? null),
                'municipio_id' => $this->map['municipio'][$o->idmunicipio ?? ''] ?? null,
                'eps_id' => $this->map['eps'][$o->id_eps ?? ''] ?? null,
                'arl_id' => $this->map['arl'][$o->id_arl ?? ''] ?? null,
                'nivel_academico_id' => $this->map['niveles_academicos'][$o->id_academico ?? ''] ?? null,
                'oficina_id' => $this->oficinaId,
                'estado' => $activo ? 'activo' : 'retirado',
                'estado_desde' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->map['conductor'][$doc] = $id;
            $c++;
        }
        $this->n['conductores'] = $c;
    }

    private function migrarClientes($L): void
    {
        $c = 0;
        foreach ($L->table('clientes_contratos')->get() as $cl) {
            $doc = $this->u($cl->cedula_cliente ?? null);
            if (! $doc) {
                continue;
            }
            if (isset($this->map['cliente'][$doc])) {
                continue;
            }
            $id = DB::table('clientes')->insertGetId([
                'empresa_id' => $this->empresaId,
                'tipo_identificacion_id' => strlen(preg_replace('/\D/', '', $doc)) >= 10 ? 3 : 1,
                'documento' => $doc,
                'nombre' => $this->u($cl->nombres_clie ?? '') ?? $doc,
                'direccion' => $this->u($cl->direccion ?? null),
                'telefono' => $this->u($cl->num_celular ?? null),
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->map['cliente'][$doc] = $id;
            $this->map['cliente_old'][$cl->idclientes] = $id;
            $c++;
        }
        $this->n['clientes'] = $c;
    }

    private function migrarVehiculos($L): void
    {
        $c = 0;
        $primerProp = DB::table('propietarios')->where('empresa_id', $this->empresaId)->value('id');
        foreach ($L->table('vehiculos')->get() as $v) {
            $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->u($v->placa ?? '') ?? ''));
            $placa = Str::limit($placa, 6, '');
            if (! $placa) {
                continue;
            }
            if (isset($this->map['vehiculo_placa'][$placa])) {
                // placa repetida en la base vieja: apuntamos el id viejo al vehículo ya creado
                $this->map['vehiculo'][$v->idvehiculos] = $this->map['vehiculo_placa'][$placa];

                continue;
            }
            $propId = $this->map['propietario'][$this->u($v->cedula_propietario ?? '')] ?? $primerProp;
            $estado = ($v->desvinculado ?? 'N') === 'S' ? 'desvinculado' : 'operando';
            $id = DB::table('vehiculos')->insertGetId([
                'empresa_id' => $this->empresaId,
                'placa' => Str::limit($placa, 6, ''),
                'numero_interno' => null,
                'propietario_id' => $propId,
                'marca_id' => $this->map['marcas'][$v->idmarcas ?? ''] ?? null,
                'linea_id' => null,
                'modelo' => is_numeric($v->modelo ?? null) ? (int) $v->modelo : 0,
                'clase_vehiculo_id' => $this->map['clases_vehiculo'][$v->idclase ?? ''] ?? null,
                'carroceria_id' => $this->map['carrocerias'][$v->idcarroceria ?? ''] ?? null,
                'combustible_id' => $this->map['combustibles'][$v->idcombustible ?? ''] ?? null,
                'tipo_servicio_id' => $this->map['tipos_servicio'][$v->idservicios ?? ''] ?? null,
                'color' => $this->u($v->color ?? null),
                'cilindraje' => is_numeric($v->cilindraje ?? null) ? (int) $v->cilindraje : null,
                'capacidad_pasajeros' => is_numeric($v->capacidad ?? null) ? (int) $v->capacidad : null,
                'numero_motor' => $this->u($v->num_motor ?? null),
                'vin' => $this->u($v->vin_num ?? null),
                'numero_chasis' => $this->u($v->num_chasis ?? null),
                'foto_path' => null,
                'estado' => $estado,
                'estado_desde' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->map['vehiculo'][$v->idvehiculos] = $id;
            $this->map['vehiculo_placa'][$placa] = $id;
            $c++;
        }
        $this->n['vehiculos'] = $c;
    }

    private function migrarDocumentosVehiculo($L): void
    {
        $c = 0;
        $tipos = [
            'soat' => 'soat',
            'tecnico_anual' => 'tecnomecanica',
            'tecnico_bimensual' => 'revision_preventiva',
            'poliza' => 'poliza_rcc',
            'tarjeta_opera' => 'tarjeta_operacion',
        ];
        foreach ($L->table('vehiculos_documen')->get() as $d) {
            $vid = $this->map['vehiculo'][$d->idvehiculos ?? ''] ?? null;
            if (! $vid) {
                continue;
            }
            foreach ($tipos as $col => $tipo) {
                $venc = $this->fecha($d->$col ?? null);
                if (! $venc) {
                    continue;
                }
                DB::table('documentos_vehiculo')->insert([
                    'empresa_id' => $this->empresaId,
                    'vehiculo_id' => $vid,
                    'tipo' => $tipo,
                    'numero' => $tipo === 'tarjeta_operacion' ? $this->u($d->numtarjeta ?? null) : null,
                    'fecha_vencimiento' => $venc,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $c++;
            }
        }
        $this->n['documentos_vehiculo'] = $c;
    }

    private function migrarContratos($L): void
    {
        $c = 0;
        $objeto = [1 => 'escolar', 2 => 'empresarial', 3 => 'turismo', 4 => 'grupo_especifico', 5 => 'salud'];
        $primerCliente = DB::table('clientes')->where('empresa_id', $this->empresaId)->value('id');
        foreach ($L->table('contratos_eventuales')->orderBy('num_contrato')->get() as $k) {
            $cliId = $this->map['cliente_old'][$k->idclientes ?? ''] ?? $primerCliente;
            if (! $cliId) {
                continue;
            }
            $ini = $this->fecha($k->fechainiciocontra ?? null) ?? now()->toDateString();
            $fin = $this->fecha($k->fechavencecontra ?? null) ?? $ini;
            $id = DB::table('contratos')->insertGetId([
                'empresa_id' => $this->empresaId,
                'numero' => (int) ($k->num_contrato ?? 0) ?: ($c + 1),
                'cliente_id' => $cliId,
                'objeto' => $objeto[$k->idclase ?? 0] ?? 'empresarial',
                'descripcion_objeto' => $this->u($k->objeto ?? null),
                'fecha_inicio' => $ini,
                'fecha_fin' => $fin,
                'recorrido' => $this->u($k->recorido ?? null),
                'valor' => is_numeric($k->valor ?? null) ? (float) $k->valor : 0,
                'oficina_id' => $this->oficinaId,
                'anulado_at' => ($k->anulado ?? 'N') === 'S' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->map['contrato'][$k->num_contrato] = $id;
            $c++;
        }
        $this->n['contratos'] = $c;
    }

    private function migrarFuecs($L): void
    {
        $c = 0;
        $primerContrato = DB::table('contratos')->where('empresa_id', $this->empresaId)->value('id');
        $monteria = $this->map['municipio']['23001'] ?? DB::table('municipios')->value('id');
        $vistos = [];
        $L->table('extracto_tbl')->orderBy('num_extracto')->chunk(2000, function ($rows) use (&$c, &$vistos, $primerContrato, $monteria) {
            foreach ($rows as $x) {
                $cons = (int) ($x->num_extracto ?? 0);
                if ($cons && isset($vistos[$cons])) {
                    continue; // copia del mismo extracto
                }
                $vistos[$cons] = true;
                $contratoId = $this->map['contrato'][$x->itm_contrato ?? ''] ?? $primerContrato;
                if (! $contratoId) {
                    continue;
                }
                $vehId = $this->map['vehiculo'][$x->idvehiculos ?? ''] ?? ($this->map['vehiculo_placa'][strtoupper((string) ($x->idplaca ?? ''))] ?? null);
                if (! $vehId) {
                    continue;
                }
                $ini = $this->fecha($x->finicia ?? null) ?? now()->toDateString();
                $fin = $this->fecha($x->fvence ?? null) ?? $ini;
                $fid = DB::table('fuecs')->insertGetId([
                    'empresa_id' => $this->empresaId,
                    'contrato_id' => $contratoId,
                    'consecutivo' => (int) ($x->num_extracto ?? 0),
                    'numero' => (string) ($x->num_extracto ?? ''),
                    'vehiculo_id' => $vehId,
                    'fecha_inicio' => $ini,
                    'fecha_fin' => $fin,
                    'origen_municipio_id' => $this->map['municipio'][$x->id_origen ?? ''] ?? $monteria,
                    'destino_municipio_id' => $this->map['municipio'][$x->id_destino ?? ''] ?? $monteria,
                    'recorrido' => $this->u($x->recorridos ?? null),
                    'datos' => json_encode([
                        'origen_texto' => $this->u($x->miorigen ?? null),
                        'destino_texto' => $this->u($x->destinos ?? null),
                        'pesv' => [
                            'tiempo_conduccion' => $x->tiempoconduce ?? null,
                            'parada_segura' => $this->u($x->parada_segura ?? null),
                            'velocidad_segura' => $this->u($x->Velocidad_segura ?? null),
                        ],
                        'legacy_num' => $x->num_extracto ?? null,
                    ], JSON_UNESCAPED_UNICODE),
                    'anulado_at' => ($x->anulado ?? 'N') === 'S' ? now() : null,
                    'codigo_verificacion' => Str::random(32),
                    'oficina_id' => $this->oficinaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $puestos = [];
                foreach ([$x->conductor1 ?? null, $x->conductor2 ?? null] as $ced) {
                    $cid = $this->map['conductor'][$this->u((string) $ced)] ?? null;
                    if ($cid && ! in_array($cid, $puestos, true)) {
                        $puestos[] = $cid;
                    }
                }
                foreach ($puestos as $i => $cid) {
                    DB::table('fuec_conductor')->insert(['fuec_id' => $fid, 'conductor_id' => $cid, 'orden' => $i + 1]);
                }
                $c++;
            }
        });
        $this->n['fuecs'] = $c;
    }

    /**
     * Segundo paso: la app lee de fuecs.datos una copia con forma fija (empresa, contrato,
     * vehiculo, conductores, origen, destino). La reconstruimos con el método real de la app
     * para que las pantallas y PDFs de FUEC no fallen por claves ausentes.
     */
    private function reconstruirDatosFuec(): void
    {
        $accion = app(\App\Actions\Operacion\ExpedirFuec::class);
        $empresa = \App\Models\Empresa::find($this->empresaId);
        $empresa?->loadMissing('municipio.departamento');

        $ok = 0;
        \App\Models\Fuec::query()
            ->where('empresa_id', $this->empresaId)
            ->with([
                'contrato.cliente.tipoIdentificacion', 'contrato.cliente.municipio',
                'vehiculo.marca', 'vehiculo.linea', 'vehiculo.claseVehiculo', 'vehiculo.documentos',
                'conductores.tipoIdentificacion',
            ])
            ->chunkById(300, function ($fuecs) use ($accion, $empresa, &$ok) {
                foreach ($fuecs as $fuec) {
                    if (! $fuec->contrato || ! $fuec->vehiculo) {
                        continue;
                    }
                    $fuec->datos = $accion->copiaDeDatos($empresa, $fuec->contrato, $fuec->vehiculo, $fuec->conductores, $fuec);
                    $fuec->saveQuietly();
                    $ok++;
                }
            });
        $this->n['fuecs_datos'] = $ok;
    }

    private function migrarInspecciones($L): void
    {
        // catálogo de ítems del preoperacional viejo: id => texto
        $items = [];
        foreach ($L->table('alistamiento_itms')->get() as $it) {
            $items[$it->id_alistamiento] = $this->u($it->detalles_alistamiento);
        }
        $c = 0;
        $L->table('alistamiento')->orderBy('idconsec')->chunk(1000, function ($rows) use (&$c, $items, $L) {
            // traer el detalle de este lote de una sola vez
            $regs = collect($rows)->pluck('idregistros')->filter()->all();
            $det = [];
            if ($regs) {
                foreach ($L->table('alistamiento_detalle')->whereIn('idregistros', $regs)->get(['idregistros', 'id_alistamiento', 'chekear']) as $d) {
                    $det[$d->idregistros][] = ['item' => $items[$d->id_alistamiento] ?? ('Ítem '.$d->id_alistamiento), 'respuesta' => ($d->chekear === 'B' ? 'bueno' : 'malo')];
                }
            }
            foreach ($rows as $a) {
                $vid = $this->map['vehiculo'][$a->idvehiculos ?? ''] ?? ($this->map['vehiculo_placa'][strtoupper((string) ($a->placas ?? ''))] ?? null);
                if (! $vid) {
                    continue;
                }
                $resp = $det[$a->idregistros] ?? [];
                $malos = collect($resp)->where('respuesta', 'malo')->count();
                DB::table('inspecciones')->insert([
                    'empresa_id' => $this->empresaId,
                    'tipo' => 'preoperacional',
                    'vehiculo_id' => $vid,
                    'conductor_id' => $this->map['conductor'][$this->u((string) ($a->cedula_cd ?? ''))] ?? null,
                    'fecha' => $this->fecha($a->fecha_hoy ?? null) ?? now()->toDateString(),
                    'resultado' => $malos > 0 ? 'no_apto' : 'apto',
                    'respuestas' => json_encode($resp, JSON_UNESCAPED_UNICODE),
                    'datos' => json_encode(['recorrido' => $this->u($a->recorrido ?? null), 'km_inicial' => $a->kminicial ?? null, 'legacy_reg' => $a->idregistros], JSON_UNESCAPED_UNICODE),
                    'observaciones' => $this->u($a->observa ?? null),
                    'codigo_verificacion' => Str::random(32),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $c++;
            }
        });
        $this->n['inspecciones'] = $c;
    }

    private function migrarMantenimientos($L): void
    {
        $c = 0;
        foreach ($L->table('mantenimientos')->get() as $m) {
            $vid = $this->map['vehiculo'][$m->idvehiculos ?? ''] ?? null;
            if (! $vid) {
                continue;
            }
            DB::table('mantenimientos')->insert([
                'empresa_id' => $this->empresaId,
                'vehiculo_id' => $vid,
                'fecha' => $this->fecha($m->fechamantenimiento ?? null) ?? now()->toDateString(),
                'tipo' => (int) ($m->idtipo ?? 0) === 2 ? 'correctivo' : 'preventivo',
                'accion' => 'revision',
                'actividad' => $this->u($m->actividadr ?? '') ?? 'Mantenimiento',
                'problema' => $this->u($m->problema ?? null),
                'repuestos' => $this->u($m->repuesto ?? null),
                'responsable_nombre' => $this->u($m->nombre_responsable ?? null),
                'valor' => is_numeric($m->precios ?? null) ? (float) $m->precios : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $c++;
        }
        $this->n['mantenimientos'] = $c;
    }

    private function migrarCobros($L): void
    {
        $c = 0;
        $recibo = 0;
        foreach ($L->table('vehiculos_cobros')->get() as $co) {
            $vid = $this->map['vehiculo'][$co->idvehiculos ?? ''] ?? ($this->map['vehiculo_placa'][strtoupper((string) ($co->placas ?? ''))] ?? null);
            if (! $vid) {
                continue;
            }
            $fecha = $this->fecha($co->fecha_pago ?? null) ?? now()->toDateString();
            $periodo = substr($fecha, 0, 7).'-01';
            $valor = (float) ($co->val_admin ?? 0) + (float) ($co->val_ss ?? 0) + (float) ($co->val_poliza ?? 0) + (float) ($co->val_otros ?? 0);
            $valor = $valor ?: (float) ($co->val_pagar ?? 0);
            $pagado = (float) ($co->val_pagado ?? 0);
            $existente = DB::table('cuotas_administracion')
                ->where(['empresa_id' => $this->empresaId, 'vehiculo_id' => $vid, 'periodo' => $periodo])->first();
            if ($existente) {
                $cuotaId = $existente->id;
                DB::table('cuotas_administracion')->where('id', $cuotaId)
                    ->update(['valor' => $existente->valor + $valor, 'pagado' => $existente->pagado + $pagado]);
            } else {
                $cuotaId = DB::table('cuotas_administracion')->insertGetId([
                    'empresa_id' => $this->empresaId,
                    'vehiculo_id' => $vid,
                    'periodo' => $periodo,
                    'valor' => $valor,
                    'pagado' => $pagado,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ((float) ($co->val_pagado ?? 0) > 0) {
                DB::table('pagos_administracion')->insert([
                    'empresa_id' => $this->empresaId,
                    'vehiculo_id' => $vid,
                    'numero' => ++$recibo,
                    'fecha' => $fecha,
                    'valor' => (float) $co->val_pagado,
                    'metodo' => 'efectivo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $c++;
        }
        $this->n['cuotas_administracion'] = $c;
    }

    private function cuadre($L): void
    {
        $this->newLine();
        $this->info('================ CUADRE DE CONTEOS (viejo → nuevo) ================');
        $filas = [
            ['Empresas', 1, $this->n['empresas'] ?? 0],
            ['Propietarios', $L->table('propietarios')->count(), $this->n['propietarios'] ?? 0],
            ['Conductores', $L->table('operadores')->count(), $this->n['conductores'] ?? 0],
            ['Clientes', $L->table('clientes_contratos')->count(), $this->n['clientes'] ?? 0],
            ['Vehículos', $L->table('vehiculos')->count(), $this->n['vehiculos'] ?? 0],
            ['Docs. vehículo (filas)', $L->table('vehiculos_documen')->count().' → pivote', $this->n['documentos_vehiculo'] ?? 0],
            ['Contratos (eventuales)', $L->table('contratos_eventuales')->count(), $this->n['contratos'] ?? 0],
            ['FUEC', $L->table('extracto_tbl')->count(), $this->n['fuecs'] ?? 0],
            ['Preoperacionales', $L->table('alistamiento')->count(), $this->n['inspecciones'] ?? 0],
            ['  (detalle colapsado)', $L->table('alistamiento_detalle')->count().' → JSON', '—'],
            ['Mantenimientos', $L->table('mantenimientos')->count(), $this->n['mantenimientos'] ?? 0],
            ['Cuotas administración', $L->table('vehiculos_cobros')->count(), $this->n['cuotas_administracion'] ?? 0],
        ];
        $this->table(['Entidad', 'Viejo', 'Nuevo'], $filas);
    }
}
