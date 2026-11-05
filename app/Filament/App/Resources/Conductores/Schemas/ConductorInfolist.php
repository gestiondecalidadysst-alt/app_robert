<?php

namespace App\Filament\App\Resources\Conductores\Schemas;

use App\Actions\Pesv\GuardarFirmaConductor;
use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Support\Pdf\Imagenes;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Hoja de vida del conductor: avisos, resumen, semáforo de documentos y datos en pestañas.
 * Documentos (historial), vehículos asignados y novedades van en las pestañas inferiores.
 */
final class ConductorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('No puede operar')
                ->danger()
                ->icon('heroicon-o-exclamation-triangle')
                ->columnSpanFull()
                ->description(fn (Conductor $record) => new HtmlString(
                    collect($record->bloqueosOperacion())->map(fn (string $m) => '• '.e($m))->implode('<br>')
                ))
                ->visible(fn (Conductor $record) => ! $record->puedeOperar()),

            Callout::make('Comparendos')
                ->warning()
                ->icon('heroicon-o-scale')
                ->columnSpanFull()
                ->description(fn (Conductor $record) => sprintf(
                    '%s · %d comparendo(s) según la consulta en el SIMIT del %s.',
                    $record->comparendos_estado?->getLabel(),
                    $record->comparendos_cantidad,
                    $record->comparendos_consultado_el?->format('d/m/Y') ?? 'sin fecha',
                ))
                ->visible(fn (Conductor $record) => $record->tieneComparendos()),

            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 5])
                ->schema([
                    ImageEntry::make('foto_path')
                        ->hiddenLabel()
                        ->disk('public')
                        ->circular()
                        ->imageHeight(110)
                        ->visible(fn (Conductor $record) => filled($record->foto_path)),
                    Grid::make(['default' => 2, 'md' => 3])
                        ->columnSpan(fn (Conductor $record) => filled($record->foto_path) ? 4 : 5)
                        ->schema([
                            TextEntry::make('nombre_completo')->label('Nombre')->weight('bold'),
                            TextEntry::make('documento')
                                ->label('Identificación')
                                ->formatStateUsing(fn (Conductor $record, string $state) => trim(($record->tipoIdentificacion?->codigo ?? '').' '.$state)),
                            TextEntry::make('estado')->badge()
                                ->helperText(fn (Conductor $record) => $record->estado_desde ? 'Desde '.$record->estado_desde->format('d/m/Y') : null),
                            TextEntry::make('licencia')
                                ->label('Licencia')
                                ->state(fn (Conductor $record) => $record->licencia()?->categoriaLicencia?->codigo ?? 'Sin registrar')
                                ->badge()
                                ->color(fn (Conductor $record) => $record->licencia()?->categoriaLicencia?->esServicioPublico() ? 'gray' : 'danger'),
                            TextEntry::make('telefono')->label('Celular')->placeholder('—'),
                            TextEntry::make('vehiculos')
                                ->label('Vehículos asignados')
                                ->state(fn (Conductor $record) => $record->asignacionesVigentes
                                    ->map(fn (AsignacionVehiculo $a) => '<span class="ruta-placa">'.e($a->vehiculo?->placa).'</span>'.($a->principal ? ' <small>principal</small>' : ''))
                                    ->implode(' '))
                                ->html()
                                ->placeholder('Ninguno'),
                        ]),
                ]),

            Section::make('Documentos vigentes')
                ->icon('heroicon-o-shield-check')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 5])
                ->schema(collect(TipoDocumentoConductor::cases())->map(
                    fn (TipoDocumentoConductor $tipo) => TextEntry::make("documento_{$tipo->value}")
                        ->label($tipo->corto().($tipo->obligatorio() ? '' : ' (opcional)'))
                        ->state(fn (Conductor $record) => $record->documentoVigente($tipo)?->fecha_vencimiento?->format('d/m/Y') ?? 'Sin registrar')
                        ->badge()
                        ->color(function (Conductor $record) use ($tipo) {
                            $estado = $record->estadoDocumento($tipo);

                            return ($estado === EstadoDocumento::Faltante && ! $tipo->obligatorio()) ? 'gray' : $estado->getColor();
                        })
                        ->helperText(fn (Conductor $record) => match ($record->estadoDocumento($tipo)) {
                            EstadoDocumento::Vencido => 'Vencido',
                            EstadoDocumento::PorVencer => 'Vence en '.$record->documentoVigente($tipo)->diasParaVencer().' días',
                            default => null,
                        })
                )->all()),

            Tabs::make('detalle')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Datos personales')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('fecha_nacimiento')->label('Nacimiento')->date('d/m/Y')->placeholder('—')
                                ->helperText(fn (Conductor $record) => $record->fecha_nacimiento ? $record->fecha_nacimiento->age.' años' : null),
                            TextEntry::make('genero')->label('Género')->formatStateUsing(fn (?string $state) => Conductor::GENEROS[$state] ?? $state)->placeholder('—'),
                            TextEntry::make('grupo_sanguineo')->label('RH')->placeholder('—'),
                            TextEntry::make('nivelAcademico.nombre')->label('Nivel académico')->placeholder('—'),
                            TextEntry::make('email')->label('Correo')->placeholder('—'),
                            TextEntry::make('direccion')->label('Dirección')->placeholder('—'),
                            TextEntry::make('municipio.nombre_completo')->label('Municipio')->placeholder('—'),
                            TextEntry::make('oficina.nombre')->label('Oficina')->placeholder('—'),
                            TextEntry::make('fecha_ingreso')->label('Ingreso')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('contacto_emergencia')->label('Contacto de emergencia')->placeholder('—')
                                ->helperText(fn (Conductor $record) => $record->telefono_emergencia),
                            TextEntry::make('observaciones')->placeholder('—')->columnSpanFull(),
                        ]),
                    Tab::make('Seguridad social')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('eps.nombre')->label('EPS')->placeholder('—'),
                            TextEntry::make('arl.nombre')->label('ARL')->placeholder('—'),
                            TextEntry::make('fondo_pensiones')->label('Fondo de pensiones')->placeholder('—'),
                        ]),
                    Tab::make('Comparendos')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('comparendos_estado')->label('Situación')->badge()->placeholder('Sin consultar'),
                            TextEntry::make('comparendos_cantidad')->label('Cantidad'),
                            TextEntry::make('comparendos_consultado_el')->label('Consultado el')->date('d/m/Y')->placeholder('—'),
                        ]),
                    Tab::make('Firma y portal')
                        ->icon(fn (Conductor $record) => $record->tieneFirma() && $record->user_id ? null : 'heroicon-m-exclamation-circle')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            ImageEntry::make('firma')
                                ->label('Firma registrada')
                                ->state(fn (Conductor $record) => Imagenes::deDisco($record->firma_path, GuardarFirmaConductor::DISCO))
                                ->imageHeight(80)
                                ->extraImgAttributes(['style' => 'background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:4px'])
                                ->placeholder('Sin firma. Regístrela en Editar → Firma, o el conductor la registra al entrar al portal.')
                                ->helperText(fn (Conductor $record) => $record->firma_actualizada_at ? 'Desde el '.$record->firma_actualizada_at->format('d/m/Y h:i a') : null),
                            TextEntry::make('portal')
                                ->label('Portal del conductor')
                                ->state(fn (Conductor $record) => match (true) {
                                    ! $record->usuario => 'Sin acceso',
                                    ! $record->usuario->activo => 'Usuario inactivo',
                                    $record->usuario->debe_cambiar_clave => 'Con clave temporal (aún no ha entrado)',
                                    default => 'Activo',
                                })
                                ->badge()
                                ->color(fn (string $state) => $state === 'Activo' ? 'success' : ($state === 'Sin acceso' ? 'gray' : 'warning'))
                                ->helperText(fn (Conductor $record) => $record->usuario
                                    ? 'Ingresa en '.route('conductor.ingresar').' con su documento '.$record->documento.'.'
                                        .($record->usuario->ultimo_acceso_at ? ' Último ingreso: '.$record->usuario->ultimo_acceso_at->format('d/m/Y h:i a').'.' : '')
                                    : 'Use «Acceso al portal» para crearle usuario y clave.'),
                        ]),
                ]),
        ]);
    }
}
