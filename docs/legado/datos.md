# Modelo de datos observado (legado)

Inferido desde formularios y tablas (no se tuvo acceso a la base de datos). Nombres entre paréntesis = `name` del campo en el formulario.

## Entidades principales
- **Empresa** (nit, rasonsocial, year_habilita, rosolucion, codterritorial, mpio, direccion, telempresa, emails, representante, cedula, ccexpedida)
- **Usuario** (doc_usuario, nom_usuario, ape_usuario, idgeneros, iddespachos -> Oficina, user_nom, mi_pass, idnivel -> Nivel, foto)
- **Oficina/Despacho** (nombre) — 2 registros.
- **Nivel** (rol) — 5 registros; **Menú** / **Submenú** (menu_ppal, sub_menus, iconos, archivo_link) con permisos por nivel.
- **Propietario** (cedulas, nombres, apellidos, fechanace, idgeneros, fechaingreso, numcontato, emails, direccion, ciudad -> Municipio)
- **Vehículo** (placa, idmarcas, idlineas, modelo, cilindraje, color, idservicios, idclaseve, idcarroceria, idscombustibles, capacidadkg, nmotor, numvin, numchasis, idspropietarios -> Propietario, puertas, fecha_expide, idstransitos -> Municipio, ficha técnica: potencia_hp, num_ejes, frenos, llantas, suspensión…, foto)
- **Documentos vehículo** (1:1 con vehículo, columnas fijas): mecanica_vence, preventiva_vence, poliza_vence, soat_vence, tarjeta_numero, tarjeta_vence, idconvenios, idempresa_afi -> Empresa afiliada, fecha_convenio, num_interno, archivo PDF
- **Novedad vehículo** (fecha_novedad, idsnovedad: SUSPENDER/DESVINCULACION/TALLERES, observacion, fecha_activar)
- **Operador/Conductor** (documento, nombres, apellidos, idgeneros, numcel, foto, + datos de seguridad social)
- **Documentos operador** (licencia_vence, idcategorias, idservicios, vence_salud, nombre_organismo, comparendo, can_comparedo, id_estado)
- **Novedad operador** (fecha_novedad, RETIRO/SUSPENDER, observacion, fecha_activar)
- **Contratante/Cliente** (cc_contratante, cc_expedida, nom_contratante, direc, cell) — también clientes de tiquetes (cc, nombres, apellidos, celular).
- **Contrato eventual** (consecutivo, finicial, fvence, hora_inicio, hora_fin, recoridos, placa(s), clase, capaci, cant_pasajeros, contratante, responsable, mivalor, valcontrato, id_pagos CONTADO/CREDITO, pagar, IdContratos tipo, objetoc, usuario, oficina)
- **Pago de contrato** (contrato, fecha_pago, valorPagado, codMetodo, obspago)
- **Extracto (FUEC)** (número control, número extracto, contratante/contrato, fecha_inicio, fecha_fin, idcontratos tipo, horasConduce, placa, idorigen, iddestino -> Municipio, horasale, dir_recogida, recorridos, idconductor1, idconductor2, usuario, oficina, nombre_pdf)
- **Alistamiento diario** (placa, conductor, kminicial, recorrido, observacion, ítems check 1..N, firma base64, fecha/hora)
- **Inspección mensual** (placa, conductor, km, fecha, respuesta S/N/NA + observación por ítem)
- **Planilla** (consecutivo, placa, conductor, fecha, hora salida, origen, destino, val_planilla, valadmin, valsso, oficina, usuario)
- **Tiquete** (planilla, cliente, destino, num_puesto, valor)
- **Turno** (placa, oficina, orden)
- **Mantenimiento** (placa, tipo, acción, fecha, actividad, problema, repuesto, centro, responsable, precio, numfactura, archivo)
- **Combustible** (num_planilla, placa, origen, destino, combustible/tarifa, galones por km)
- **Pago administración** (placa, valor a pagar, propietario/afiliado, fecha_pago, valor pagado, observaciones)

## Catálogos
Marca, Línea(marca), Clase(valor administración), Carrocería, Combustible, Servicio, EPS, ARL, Nivel académico, Género, Empresa afiliada, Tipo convenio, Categoría licencia, Estado comparendo, Ítems alistamiento, Ítems inspección, Trayecto, Tarifa(origen, destino, valor), Municipio(departamento), Método de pago, Forma de pago, Tipo de contrato.

## Relaciones clave
- Propietario 1–N Vehículo; Vehículo N–1 Marca/Línea/Clase/…
- Contrato 1–N Pago; Contrato 1–N Extracto; Extracto N–1 Vehículo; Extracto N–2 Conductor.
- Planilla N–1 Vehículo, N–1 Conductor, 1–N Tiquete; Tiquete N–1 Cliente.
- Vehículo 1–N Alistamiento, Inspección, Mantenimiento, Pago administración, Novedad.

## Pendiente
- Confirmar con el código fuente/BD: nombres reales de tablas, campos de operador no extraídos, umbrales de validación del extracto, formato del PDF del FUEC.
