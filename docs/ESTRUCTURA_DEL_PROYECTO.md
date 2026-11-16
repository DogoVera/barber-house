# Guía para estudiar y modificar Barber House

Proyecto **Gestión de Turnos y Contabilidad**, Práctica Profesionalizante 3, 2026.
Implementación: PHP, **CodeIgniter 3.1.13**, MySQL/MariaDB y XAMPP. Esta guía
describe los archivos reales después de la reorganización en español.

## 1. Cómo se recorre una petición

```text
Navegador → index.php → config/routes.php → Controlador → Modelo → MySQL
                                               ↓          ↓
                                               └── datos ─┘
                                                   ↓
                                                  Vista → Navegador
```

- **Controlador:** sesión, permiso, lectura/validación del formulario y coordinación.
- **Modelo:** consultas, escrituras, validaciones de persistencia, transacciones y bloqueos.
- **Vista:** campos, tablas, navegación y presentación de datos; no ejecuta SQL.
- **JavaScript:** ayuda visual y consulta de disponibilidad; el servidor vuelve a validar.

Los nombres de tablas, columnas, estados y campos POST continúan en inglés para
conservar el esquema y los contratos de la aplicación. Por ejemplo, el campo
`first_name` sigue llegando a la columna `clients.first_name`.

## 2. Organización de carpetas

```text
application/
├── config/                 Rutas, conexión, carga automática y entorno test
├── controllers/            Los nueve módulos de la aplicación
├── core/MY_Controller.php  Sesión, permisos y presentación compartidos
├── helpers/                Funciones pequeñas de formato y validación
├── models/                 Los siete modelos de persistencia
├── views/
│   ├── autenticacion/      Inicio de sesión
│   ├── panel/              Resumen de la jornada
│   ├── clientes/           Lista, formulario y detalle/historial
│   ├── peluqueros/         Lista, formulario, disponibilidad y reasignación
│   ├── servicios/          Lista y formulario del catálogo
│   ├── turnos/             Agenda, formulario y detalle
│   ├── cobros/             Lista, alta, corrección y formas de pago
│   ├── contabilidad/       Resumen/liquidaciones y egresos
│   ├── usuarios/           Lista y formulario de cuentas
│   ├── plantilla/          Cabecera, menú y pie comunes
│   ├── compartidas/        Errores de formulario y paginación
│   └── errors/             Vistas de error con nombres esperados por CI3
├── cache/sessions/         Sesiones del uso normal
└── logs/                   Registros del uso normal
assets/                     Recursos locales CSS, JavaScript y logotipo
database/barber_house.sql   Único instalador de la base nueva
docs/                       Requerimientos, decisiones y guías
system/                     Núcleo original de CodeIgniter 3.1.13
tools/pruebas/              Verificación estática y funcional aislada
```

## 3. Controladores

Rutas de esta tabla relativas a `application/controllers/`.

| Archivo | Para qué sirve | Qué se puede modificar |
|---|---|---|
| `Autenticacion.php` | Inicio/cierre de sesión con Usuario_model | Mensajes y flujo del acceso; conservar hash, sesión y POST de salida |
| `Panel_principal.php` | Agenda, contadores e indicadores del día | Preparación de datos del panel y selección de indicadores por rol |
| `Clientes.php` | Alta, búsqueda, edición, historial, archivo y restauración | Validaciones y flujo de formularios de clientes |
| `Peluqueros.php` | Profesionales, jornadas, servicios, porcentaje y reasignación | Lectura/validación del formulario y preparación de disponibilidad |
| `Servicios.php` | Catálogo, precio, duración y estado de servicios | Validaciones y mensajes de servicios |
| `Turnos.php` | Agenda, atención espontánea, disponibilidad JSON y estados | Recepción de solicitudes, filtros y validaciones simples |
| `Cobros.php` | Cobro diario, correcciones, anulaciones y formas de pago | Validaciones de importe/forma y flujo de las operaciones autorizadas |
| `Contabilidad.php` | Resumen, liquidaciones, movimientos y egresos | Preparación del período, datos del resumen y formularios de gastos |
| `Usuarios.php` | Administración de cuentas y perfiles | Validaciones de cuentas; conservar protección de la propia cuenta |

Cada clase coincide exactamente con el nombre del archivo. `index()` se conserva
como entrada convencional de CI3. Las acciones se llaman `nuevo`, `guardar`,
`editar`, `actualizar`, `ver`, `eliminar` y `restaurar` donde corresponden.
Los métodos privados preparan datos sin convertirse en rutas públicas.

## 4. Modelos y tablas

Rutas relativas a `application/models/`. Los métodos devuelven arrays para las
consultas; las inserciones devuelven id o `FALSE`. Las operaciones transaccionales
documentan su resultado y el motivo de rechazo.

| Archivo | Para qué sirve | Qué se puede modificar |
|---|---|---|
| `Cliente_model.php` | Clientes e historial; clients, appointments, barbers, appointment_services, services | Filtros, consultas y escrituras de clientes |
| `Peluquero_model.php` | barbers, barber_schedules, barber_services; consulta reservas/servicios y clients | Jornadas y consultas; mantener la validación atómica de reservas futuras |
| `Servicio_model.php` | Catálogo services; consulta appointment_services y appointments | Búsqueda, guardado y bajas del catálogo, sin recalcular snapshots |
| `Turno_model.php` | appointments y appointment_services; une clientes, peluqueros, servicios y cobros | Disponibilidad, intervalos y consultas; conservar bloqueos y snapshots |
| `Cobro_model.php` | payments/payment_methods; relaciona appointments, barbers y clients | Consultas de ingresos y liquidación, guardado de cobros y formas |
| `Egreso_model.php` | expenses/expense_categories y autoría desde users | Consultas, registro, corrección y anulación de gastos |
| `Usuario_model.php` | users y roles | Consultas y escrituras de cuentas/perfiles; acceso solo de activos |

Para ubicar una consulta rápidamente: buscar el nombre de la tabla en el modelo
indicado. `Turno_model` utiliza además `Peluquero_model` y `Servicio_model` para
reutilizar comprobaciones de jornada, servicios y actividad.

## 5. Todas las vistas de los módulos

Rutas relativas a `application/views/`. Cambiar el HTML en estas vistas permite
modificar campos o presentación; para cambiar una validación hay que seguir
también el controlador y el modelo correspondientes.

| Archivo | Para qué sirve | Qué se puede modificar |
|---|---|---|
| `autenticacion/inicio_sesion.php` | Acceso con usuario y contraseña | Textos y campos del formulario de acceso |
| `panel/resumen_jornada.php` | Indicadores, agenda y accesos rápidos | Presentación de indicadores autorizados |
| `clientes/listado_clientes.php` | Búsqueda y lista de clientes | Columnas, etiquetas y presentación de acciones |
| `clientes/formulario_cliente.php` | Alta y edición de datos personales | Campos y elementos visuales del cliente |
| `clientes/detalle_cliente.php` | Datos e historial | Presentación del cliente y sus atenciones |
| `peluqueros/listado_peluqueros.php` | Profesionales y accesos a su agenda | Columnas y etiquetas |
| `peluqueros/formulario_peluquero.php` | Datos, porcentaje, siete días y servicios | Campos; los nombres day_1/start_1/end_1 se coordinan con el controlador |
| `peluqueros/disponibilidad_peluquero.php` | Jornada, ocupados y espacios libres | Presentación del resultado y enlaces de alta |
| `peluqueros/reasignar_turnos.php` | Cambiar profesional/fecha/hora de una reserva | Campos de la reasignación y mensajes |
| `servicios/listado_servicios.php` | Precios y duraciones del catálogo | Columnas y presentación |
| `servicios/formulario_servicio.php` | Alta/edición de servicio | Campos de nombre, precio, duración y actividad |
| `turnos/listado_turnos.php` | Agenda diaria e historial paginado | Filtros visibles, columnas y acciones |
| `turnos/formulario_turno.php` | Cliente, peluquero, servicios, fecha/hora y estado | Campos y conexión visual de disponibilidad con data-* |
| `turnos/detalle_turno.php` | Datos, servicios históricos y cobro del turno | Presentación y botones autorizados |
| `cobros/listado_cobros.php` | Cobros filtrados y acciones económicas | Columnas, filtros y presentación de acciones por rol |
| `cobros/formulario_cobro.php` | Registro del importe real y forma de pago | Campos y textos de confirmación |
| `cobros/editar_cobro.php` | Corrección administrativa | Campos de importe/forma/nota |
| `cobros/formas_pago.php` | Listado y alta de medios de pago | Nombre, estado y formulario de alta |
| `contabilidad/resumen_contabilidad.php` | Totales, liquidación, gasto y movimientos | Presentación de resultados ya preparados |
| `contabilidad/listado_egresos.php` | Alta y lista filtrada de gastos | Campos de alta y columnas del listado |
| `contabilidad/formulario_egreso.php` | Corrección y anulación de gasto | Campos de fecha/categoría/concepto/monto |
| `usuarios/listado_usuarios.php` | Cuentas, perfiles, actividad y acceso | Columnas y botones de cuentas |
| `usuarios/formulario_usuario.php` | Alta/edición de cuenta | Campos y textos; clave vacía conserva la anterior al editar |
| `plantilla/cabecera.php` | Documento HTML, título y CSS local | Metadatos y carga de estilos |
| `plantilla/menu_lateral.php` | Navegación, usuario, tema y apertura del contenido | Enlaces y selección visual del módulo activo |
| `plantilla/pie.php` | Cierre del documento y JavaScript local | Carga de scripts comunes |
| `compartidas/errores_formulario.php` | Lista escapada de errores del controlador | Presentación uniforme de errores |
| `compartidas/paginacion.php` | Anterior/siguiente conservando filtros | Textos y enlaces de paginación |
| `errors/html/error_404.php` | Respuesta a una página o registro inexistente | Mensaje de error manteniendo el nombre esperado por CI3 |
| `errors/html/error_db.php` | Error de conexión/consulta | Presentación del error de MySQL |
| `errors/html/error_general.php` | Error HTTP general (por ejemplo permiso) | Mensajes comunes |
| `errors/html/error_php.php` | Avisos PHP del entorno de desarrollo | Presentación del diagnóstico |
| `errors/html/error_exception.php` | Excepción no controlada | Presentación del diagnóstico |

## 6. Infraestructura, recursos y pruebas

| Archivo | Para qué sirve | Qué se puede modificar |
|---|---|---|
| `index.php` | Entrada única; define entorno/rutas y carga CI3 | Entorno y ubicación técnica, no reglas de negocio |
| `.htaccess` | Reescritura Apache y protección de carpetas internas | Reglas del despliegue Apache |
| `application/core/MY_Controller.php` | Sesión, permisos, render, POST y paginación de 25 | Comportamiento compartido; comprobar ambos roles al cambiarlo |
| `application/helpers/barberia_helper.php` | h, money, estados, fechas, importes, recursos y page_url | Formatos y validaciones pequeñas reutilizadas |
| `application/config/autoload.php` | Carga automática de librerías y helpers | Recursos comunes que se cargan en todas las peticiones |
| `application/config/config.php` | URL base, sesión, CSRF y configuración general | Configuración de CI3 según el entorno |
| `application/config/database.php` | Conexión MySQL normal | Host, usuario, clave y nombre de la instalación |
| `application/config/routes.php` | URL públicas y alias anteriores | Correspondencia URL/controlador/método |
| `application/config/constants.php` | Constantes técnicas de CI3 | Constantes de archivos y ejecución, si se necesita |
| `application/config/migration.php` | Configuración técnica de migraciones | Se conserva deshabilitada; la instalación nueva usa el SQL único |
| `application/config/test/database.php` | Destino aislado validado para integración | Nombres/puerto de prueba, manteniendo el bloqueo de la base real |
| `application/config/test/config.php` | Sesiones y logs fuera del proyecto | Ubicación de los archivos temporales de test |
| `assets/css/estilos_barberia.css` | Estilos originales y responsive | Buscar la clase CSS de la vista; paleta clara/oscura al comienzo |
| `assets/js/interfaz_barberia.js` | Tema, menú, estimados, AJAX y confirmaciones | Interacción; conservar atributos data-* de las vistas |
| `assets/img/logo.png` | Identidad visual | Imagen local del logotipo |
| `database/barber_house.sql` | Instalación nueva completa y datos demo | Esquema inicial solo ante cambio aprobado; no usar como actualización |
| `tools/pruebas/ejecutar.php` | Helpers y estructura sin MySQL | Verificaciones rápidas de funciones reutilizables |
| `tools/pruebas/verificar_codigo.php` | Lint, rutas, clases, referencias y MVC | Comprobaciones estáticas después de editar |
| `tools/pruebas/integracion.php` | Aplicación real por HTTP con una base temporal | Casos funcionales y verificación de persistencia |
| `tools/pruebas/enrutador.php` | Rutas del servidor PHP exclusivo de test | Adaptación del servidor de prueba; fija CI_ENV=test |
| `tools/pruebas/mariadb_aislada.php` | MariaDB descartable de XAMPP sin iniciar servicios | Inicialización, límites y limpieza de la instancia aislada |
| `tools/pruebas/README.md` | Cómo ejecutar y comprender las pruebas | Comandos y explicación de los resultados |
| `README.md` | Instalación y presentación del proyecto | Instrucciones de uso/instalación |
| `DATOS_DE_ACCESO.txt` | Credenciales de demostración | Datos de acceso de una instalación nueva |
| `AGENTS.md` | Reglas permanentes de mantenimiento | Acuerdos del equipo, no redefine los requerimientos |
| `docs/REQUERIMIENTOS_V1.md` | Los 71 requerimientos formales | Fuente de consulta; cambios de alcance requieren decisión del equipo |
| `docs/DECISIONES_TECNICAS.md` | Decisiones vigentes | Registrar acuerdos técnicos aprobados |
| `docs/CONTEXTO_PROYECTO.md` | Relevamiento resumido y alcance | Contexto del trabajo académico |
| `docs/ESQUEMA_BD.md` | Relación de tablas de la implementación | Guía del esquema vigente |
| `docs/MATRIZ_TRAZABILIDAD.md` | Ubicación de los requerimientos por módulo | Referencias a código y pruebas |
| `docs/ESTADO_IMPLEMENTACION_V1.md` | Estado técnico y verificaciones | Resultados realmente ejecutados |
| `docs/INFORME_REORGANIZACION.md` | Renombres, decisiones y resultados de esta tarea | Registro de la reorganización y sus comprobaciones |

Los PDF y `DER_Barber_House.png` son fuentes académicas originales. `system/`
es el núcleo del framework. Los archivos de cache/sesiones y logs son datos de
ejecución, no archivos donde implementar una pantalla o regla de negocio.

## 7. Ejemplo real: registrar un cliente

1. Abrir `clientes/nuevo`. En `config/routes.php` apunta a `Clientes::nuevo()`.
2. `Clientes.php` carga `clientes/formulario_cliente.php` con `$client = NULL`.
3. La vista genera `form_open('clientes/guardar')`, incluyendo CSRF. Sus campos
   son `first_name`, `last_name`, `phone` y `notes`.
4. `Clientes::guardar()` exige POST. `validar_datos()` comprueba los tres datos
   obligatorios y consulta `Cliente_model::buscar_duplicado()`.
5. Si hay errores, muestra el mismo formulario con los datos enviados.
6. `datos_cliente()` arma el array; `guardar()` agrega `created_at` y llama a
   `Cliente_model::registrar_cliente($data)`.
7. El modelo inserta en `clients` y devuelve el id. El controlador informa el
   resultado y redirige a `clientes/ver/{id}`.

**Dónde editar:** etiqueta/campo en la vista; obligatoriedad en `validar_datos()`;
búsqueda o escritura en `Cliente_model`. Un nuevo campo persistente requeriría
también una decisión de esquema: no basta con agregar un input.

## 8. Ejemplo real: crear un turno

1. `turnos/nuevo` llega a `Turnos::nuevo()` y `mostrar_formulario()`.
2. El controlador obtiene clientes, peluqueros y servicios de sus modelos y
   carga `turnos/formulario_turno.php`.
3. El formulario envía `client_id`, `barber_id`, `service_ids[]`, `date`, `time`,
   `status`, `notes` y `walk_in` a `turnos/guardar`.
4. `guardar()` llama a `guardar_formulario(NULL, ...)`. `validar_formulario()`
   revisa cliente/selecciones/formato y delega a `Turno_model::validar_horario()`.
5. El modelo calcula duración total, verifica servicios del peluquero, jornada
   y superposición. Devuelve resultado, motivo, inicio, fin y servicios.
6. El controlador prepara los campos y llama a `guardar_turno()`.
7. **Dentro de la transacción**, el modelo bloquea al peluquero, vuelve a validar
   y guarda `appointments` y `appointment_services` con snapshots. Al editar,
   bloquea también el turno y rechaza cualquier historial de cobro.
8. Si falla revierte; si funciona confirma y el controlador abre el detalle.

**Dónde editar:** datos visibles en la vista; recepción del POST en Turnos;
reglas de agenda en Turno_model/Peluquero_model. Mantener la segunda validación
bajo bloqueo: la disponibilidad pudo cambiar después de abrir el formulario.

## 9. Ejemplo real: disponibilidad de un peluquero

1. En `formulario_turno.php`, los atributos `data-availability-*` identifican
   peluquero, fecha, servicios y hora elegida.
2. `assets/js/interfaz_barberia.js`, función `cargarDisponibilidad()`, envía GET
   a la URL `turnos/disponibilidad` indicada por `data-url`.
3. `Turnos::disponibilidad()` lee los filtros y llama a
   `Turno_model::horarios_disponibles()`; devuelve JSON.
4. El modelo consulta la jornada de ese día y los turnos reservados/atendidos,
   suma duraciones y prueba candidatos cada **15 minutos** con `validar_horario()`.
5. Dos intervalos se superponen cuando `inicioA < finB` y `finA > inicioB`.
   Cancelados/ausentes no bloquean. Un turno que comienza justo al terminar otro
   es válido si entra en la jornada.
6. JavaScript muestra botones de hora; elegir uno solo llena el campo, no reserva.
7. `Peluqueros::disponibilidad($id)` reutiliza el mismo modelo para su pantalla.

**Dónde editar:** presentación en JavaScript/vistas; cálculo en el modelo.
No reemplazar el control de intervalos por una comparación de horas iguales.

## 10. Ejemplo real: registrar un cobro

1. `turnos/detalle_turno.php` enlaza a `cobros/nuevo/{id_turno}` si corresponde.
2. `Cobros::nuevo()` consulta el turno y las formas activas; abre
   `cobros/formulario_cobro.php`. El total de lista es una propuesta editable.
3. El POST `cobros/guardar` incluye `appointment_id`, `amount`,
   `payment_method_id` y `notes`.
4. `Cobros::guardar()` valida importe positivo y selección. Llama a
   `Cobro_model::registrar_cobro_turno()` con el usuario autor.
5. El modelo bloquea el turno, rechaza cancelados/ausentes o cobros existentes
   y verifica la forma activa. Calcula `importe × porcentaje / 100`, redondeado
   a dos decimales, y guarda el porcentaje y monto históricos en `payments`.
6. Marca el turno atendido dentro de la misma transacción. El ingreso **es ese
   cobro**, no se inserta otra fila en una tabla de ingresos.
7. Ante error hace rollback. Corrección/anulación/restauración requieren Encargado.

**Dónde editar:** formulario en la vista, validaciones en Cobros y persistencia
en Cobro_model. Al corregir se recalcula el monto usando el porcentaje histórico,
no el porcentaje actual de `barbers`.

## 11. Ejemplo real: consultar una liquidación

1. El Encargado abre `contabilidad?from=2026-10-01&to=2026-10-31`.
2. `Contabilidad::periodo_seleccionado()` corrige fechas inválidas y las ordena.
3. `index()` solicita `Cobro_model::calcular_liquidacion($from, $to)`.
4. El modelo agrupa cobros vigentes por peluquero y devuelve `attentions`
   (cantidad), `production` (importe real) y `payout` (comisiones históricas).
5. `contabilidad/resumen_contabilidad.php` muestra los datos. El porcentaje
   efectivo es solo un indicador visual ponderado, no una nueva comisión.
6. El resultado general se prepara como ingresos menos egresos menos comisiones.

**Dónde editar:** agrupación/consultas en Cobro_model; selección del período y
coordinación en Contabilidad; columnas y formato en la vista.

## 12. Ejemplo real: guardar en MySQL mediante Query Builder

Este bloque existe en `Cliente_model::registrar_cliente()`:

```php
if (!$this->db->insert('clients', $data)) {
    return FALSE;
}
return $this->db->insert_id();
```

1. El controlador ya validó y preparó `$data`, por ejemplo `first_name` y `phone`.
2. La librería `database` se carga desde `config/autoload.php` y usa
   `config/database.php` para conectar al MySQL de XAMPP.
3. Query Builder genera el INSERT y escapa los valores. No concatenar datos
   recibidos dentro de SQL manual.
4. El modelo devuelve el id o `FALSE`; el controlador decide mensaje y destino.
5. Cuando se escriben varias tablas relacionadas, como turno/servicios o
   cobro/estado, sus modelos usan `trans_begin`, `trans_commit` y `trans_rollback`.
   Los `SELECT ... FOR UPDATE` son SQL especial justificado para concurrencia.

## 13. Comprobación después de modificar manualmente

Desde la raíz del proyecto, con el PHP de XAMPP y Node disponible para la
comprobación sintáctica de JavaScript:

```text
php tools/pruebas/verificar_codigo.php
php tools/pruebas/ejecutar.php
php tools/pruebas/mariadb_aislada.php
```

El último comando utiliza MariaDB de XAMPP con datos y puerto temporales, y
limpia sus recursos al terminar. No utiliza la base principal. Si ya se dispone
de un servidor MariaDB de pruebas, el runner directo está explicado en
`tools/pruebas/README.md`.
