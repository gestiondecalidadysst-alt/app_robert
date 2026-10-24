# Registro de decisiones

| Fecha | Decisión | Motivo | Alternativas |
|---|---|---|---|
| 2026-10-24 | El análisis de la app actual se hace navegando como ADMINISTRADOR en modo solo lectura (sin guardar ni borrar). | No alterar datos de producción. | Copia del código/BD (pendiente: daría reglas exactas). |
| 2026-10-24 | Nueva versión en Laravel, multiempresa. | Decisión del usuario; sigue en PHP y el sistema se vende a varias empresas. | Next.js, Django. |
| 2026-10-24 | Se elimina el módulo Despachos (turnos, planillas, tiquetes y caja) en esta versión. | Decisión del usuario. | Mantenerlo como módulo aparte. |
| 2026-10-24 | 9 módulos: Configuración, Flota, Conductores, Operación (contratos + FUEC), PESV, Mantenimiento (+combustible), Finanzas, Gerencia, Portal conductor e integraciones. | Agrupación acordada con el usuario. | Lista de 20 módulos sin agrupar. |
| 2026-10-24 | Propuesta visual "Ruta" (pendiente de aprobación): menú lateral azul noche #0E1A2B, fondo #F3F5F8, acción azul #1F4FD8, placa como chip amarillo #FFD23F con borde negro y fuente JetBrains Mono, semáforo vigente/por vencer/vencido, tipografía Manrope. Escritorio con menú lateral; celular con barra inferior. | Look nuevo, distinto al legado, que se entienda de un vistazo. | Mantener plantilla tipo SB Admin. |
| 2026-10-24 | Estilo visual tomado de la referencia del usuario (pantalla "flonext"): menú lateral azul petróleo #1B2B3A con ítem activo #2A3E51 y barra naranja #EA6A2C, etiqueta "TU PLATAFORMA", barra superior blanca con EMPRESA + nombre (selector multiempresa) y rol + salir, acción principal naranja #C2410C (hover #9A3412, tinte #FDEEE6), fuente Inter. Se conservan placas amarillas y semáforo de estados. Reemplaza la paleta azul de "Ruta". | Preferencia del usuario. | Paleta azul/amarilla inicial. |
| 2026-10-24 | Pantalla principal = tablero de gerencia (negocio: contratado, recaudado, contratos, FUEC, servicios, vehículos que operaron, clientes, cartera) con selector Mes/Trimestre/Año; vencimientos van en pantalla operativa aparte. | Pedido del usuario. | Tablero operativo como inicio. |
| 2026-10-24 | La documentación no incluye datos personales reales (nombres, cédulas, teléfonos) de propietarios, conductores ni clientes; solo estructura y catálogos. | Privacidad. | — |
