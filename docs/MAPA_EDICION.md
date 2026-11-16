# Mapa de edición rápida (Barber House / CI3)

Guía para **editar manualmente** cualquier pantalla durante el parcial: qué archivo abrir y qué tocar según el pedido.

> **Por qué no se cambió la estructura de carpetas:** en CodeIgniter 3 las rutas son fijas por convención (`application/controllers`, `application/models`, `application/views`, `application/config/routes.php`). Mover o renombrar carpetas/archivos rompe la carga automática, las rutas y las vistas. La facilidad está en saber **qué archivo abre cada cosa** (este mapa).

## 1) Cómo ubicar cualquier pantalla

1. **URL** (lo que ves en el navegador) → buscar en `application/config/routes.php` → te da `Controlador::método`.
2. **Controlador** `application/controllers/Xxx.php` → valida, lee datos, llama al modelo y hace `render('carpeta/vista', $data)`.
3. **Vista** `application/views/carpeta/vista.php` → HTML (formularios, tablas, botones). Usa las variables que le pasa el controlador.
4. **Modelo** `application/models/Xxx_model.php` → consultas e insert/update en MySQL (`$this->db`). Sin SQL en controladores ni vistas.
5. **Compartidos**: menú `views/plantilla/menu_lateral.php`, layout `views/plantilla/cabecera.php` y `pie.php`, errores `views/compartidas/errores_formulario.php`, paginación `views/compartidas/paginacion.php`.

**Convenciones:** `index` = listado, `nuevo/guardar` = alta, `editar/actualizar` = edición, `ver` = detalle, `eliminar/restaurar/anular` = baja lógica. Forms: `form_open('ruta')` (POST+CSRF), campo `name="x"` → se lee con `$this->posted('x')`.

## 2) Recetas rápidas (qué tocar)

- **Botón (link):** en la vista → `<a class="btn" href="<?= site_url('ruta') ?>">Texto</a>`
- **Botón (acción POST):** vista `form_open('ruta/'.$id)` + `<button type="submit">`; si la acción es nueva → ruta en `routes.php` + método en controlador + lógica en modelo (si cambia BD).
- **Dropdown en formulario:** vista `<select name="campo">`; controlador `guardar/actualizar` lee/valida `$this->posted('campo')`; si se guarda → modelo insert/update (+ columna en BD si no existe). (`name` = posted = columna)
- **Dropdown como filtro (listado):** vista dentro del `<form method="get">`; controlador `index()` lee GET, lo pasa a la vista y a `filtros_paginacion`; modelo agrega `where` en consulta y contador.
- **Columna en tabla HTML:** vista agrega `<th>`, `<td>` por fila y ajusta `colspan` del vacío; si el dato falta → agregarlo al `select/join` en el modelo.
- **Fila en tabla (ej. total):** vista agrega `<tr>` en `<tbody>` calculando con los datos ya recibidos.
- **Campo nuevo en BD (demo local):** `ALTER TABLE t ADD COLUMN c VARCHAR(50) NULL;` → reflejar en modelo (select/insert/update) y vista. Nueva tabla: `CREATE TABLE ...` + `INSERT ...` y luego modelo/controlador/vistas/ruta si se usa en la app.
  - Regla del proyecto: cambios formales van en `database/updates/` y en `database/barber_house.sql`; nunca sobre base real con datos.

## 3) Archivos compartidos (útiles en casi todo)

- `application/config/routes.php` → URLs ↔ controlador/método
- `application/core/MY_Controller.php` → `render`, permisos (`require_login/manager`), POST, paginación
- `application/helpers/barberia_helper.php` → `h()`, `money()`, estados, validaciones
- `application/views/plantilla/menu_lateral.php` → sidebar/menú
- `application/views/plantilla/cabecera.php`, `pie.php` → layout
- `application/views/compartidas/errores_formulario.php`, `paginacion.php` → parciales
- `application/config/database.php` → conexión `barber_house`
- `database/barber_house.sql` → instalación nueva + datos demo
- `application/logs/log-YYYY-MM-DD.php` → errores

## 4) Mapa por módulo y pantalla

Formato: **Pantalla** — URL — `Controlador::método` — `Modelo` — `vista` — (tablas BD)

### Acceso e Inicio
- **Login** — `/login` — `Autenticacion::iniciar_sesion` — `Usuario_model` — `views/autenticacion/inicio_sesion.php` — (`users`,`roles`)
- **Logout** — `/logout` (POST) — `Autenticacion::cerrar_sesion` — — — —
- **Inicio** — `/inicio` — `Panel_principal::index` — (según panel) — `views/panel/resumen_jornada.php` — (varias)

### Clientes (todos los roles) — `controllers/Clientes.php`, `models/Cliente_model.php`, `views/clientes/*` — tabla `clients`
- **Listado/búsqueda** — `/clientes` — `Clientes::index` — `clientes/listado_clientes.php`
- **Nuevo** — `/clientes/nuevo` + POST `/clientes/guardar` — `nuevo`,`guardar` — `clientes/formulario_cliente.php`
- **Editar** — `/clientes/editar/{id}` + POST `/clientes/actualizar/{id}` — `editar`,`actualizar` — `clientes/formulario_cliente.php`
- **Detalle/historial** — `/clientes/ver/{id}` — `ver` — `clientes/detalle_cliente.php`
- **Eliminar/Restaurar** — POST `/clientes/eliminar/{id}`, `/clientes/restaurar/{id}` — `eliminar`,`restaurar` — (botones en listado/detalle)
- Campos: `first_name,last_name,phone,tipo,notes` (dropdown Tipo ya existe en el formulario).

### Agenda / Turnos (todos) — `controllers/Turnos.php`, `models/Turno_model.php` (+Cliente/Peluquero/Servicio), `views/turnos/*` — `appointments`,`appointment_services`
- **Agenda/listado** — `/turnos` — `Turnos::index` — `turnos/listado_turnos.php`
- **Nuevo turno** — `/turnos/nuevo` + POST `/turnos/guardar` — `nuevo`,`guardar` — `turnos/formulario_turno.php`
- **Atención sin reserva** — `/turnos/sin-reserva` + POST `/turnos/guardar` — `sin_reserva`,`guardar` — `turnos/formulario_turno.php`
- **Editar** — `/turnos/editar/{id}` + POST `/turnos/actualizar/{id}` — `editar`,`actualizar` — `turnos/formulario_turno.php`
- **Detalle** — `/turnos/ver/{id}` — `ver` — `turnos/detalle_turno.php`
- **Cambiar estado / Cancelar** — POST `/turnos/estado/{id}`, `/turnos/eliminar/{id}` — `cambiar_estado`,`eliminar` — (detalle)
- **Disponibilidad (AJAX)** — `/turnos/disponibilidad` — `disponibilidad` — (JSON)

### Cobros (todos; correcciones solo Encargado) — `controllers/Cobros.php`, `models/Cobro_model.php` (+Turno), `views/cobros/*` — `payments`,`payment_methods`
- **Listado** — `/cobros` — `Cobros::index` — `cobros/listado_cobros.php` (filtros GET `q,from,to,archived`; tabla: Fecha, Cliente, Peluquero, Forma, Importe, Acciones)
- **Registrar cobro** — `/cobros/nuevo/{turno_id}` + POST `/cobros/guardar` — `nuevo`,`guardar` — `cobros/formulario_cobro.php` (se llega desde detalle del turno “Marcar atendido / cobrar”)
- **Editar cobro** — `/cobros/editar/{id}` + POST `/cobros/actualizar/{id}` — `editar`,`actualizar` — `cobros/editar_cobro.php`
- **Anular/Restaurar** — POST `/cobros/eliminar/{id}`, `/cobros/restaurar/{id}` — `eliminar`,`restaurar` — (listado)
- **Formas de pago** — `/formas-pago` + POST `/formas-pago/guardar` — `formas_pago`,`guardar_forma_pago` — `cobros/formas_pago.php`
- Nota: el botón “+ Registrar cobro” del listado lleva a `/turnos`.

### Peluqueros (Encargado) — `controllers/Peluqueros.php`, `models/Peluquero_model.php`, `views/peluqueros/*` — `barbers`,`barber_schedules`,`barber_services`
- **Listado** — `/peluqueros` — `Peluqueros::index` — `peluqueros/listado_peluqueros.php`
- **Nuevo** — `/peluqueros/nuevo` + POST `/peluqueros/guardar` — `nuevo`,`guardar` — `peluqueros/formulario_peluquero.php`
- **Editar** — `/peluqueros/editar/{id}` + POST `/peluqueros/actualizar/{id}` — `editar`,`actualizar` — `peluqueros/formulario_peluquero.php`
- **Activar/Desactivar** — POST `/peluqueros/estado/{id}` — `cambiar_estado` — (botón en listado)
- **Disponibilidad** — `/peluqueros/disponibilidad/{id}` — `disponibilidad` — `peluqueros/disponibilidad_peluquero.php`
- **Reasignar turnos** — `/peluqueros/reasignar/{id}` — `reasignar` — `peluqueros/reasignar_turnos.php`
- **Eliminar/Restaurar** — POST `/peluqueros/eliminar/{id}`, `/peluqueros/restaurar/{id}` — `eliminar`,`restaurar` — (listado)

### Servicios (Encargado) — `controllers/Servicios.php`, `models/Servicio_model.php`, `views/servicios/*` — `services`
- **Listado** — `/servicios` — `Servicios::index` — `servicios/listado_servicios.php`
- **Nuevo** — `/servicios/nuevo` + POST `/servicios/guardar` — `nuevo`,`guardar` — `servicios/formulario_servicio.php`
- **Editar** — `/servicios/editar/{id}` + POST `/servicios/actualizar/{id}` — `editar`,`actualizar` — `servicios/formulario_servicio.php`
- **Eliminar/Restaurar** — POST `/servicios/eliminar/{id}`, `/servicios/restaurar/{id}` — `eliminar`,`restaurar` — (listado)

### Contabilidad y Egresos (Encargado) — `controllers/Contabilidad.php`, `models/Cobro_model.php`,`Egreso_model.php`, `views/contabilidad/*`
- **Resumen contable** — `/contabilidad` — `Contabilidad::index` — `contabilidad/resumen_contabilidad.php` — (`payments`,`expenses`)
- **Egresos (listado+alta)** — `/egresos` + POST `/egresos/guardar` — `egresos`,`guardar_egreso` — `Egreso_model` — `contabilidad/listado_egresos.php` — (`expenses`,`expense_categories`)
- **Editar egreso** — `/egresos/editar/{id}` + POST `/egresos/actualizar/{id}` — `editar_egreso`,`actualizar_egreso` — `contabilidad/formulario_egreso.php`
- **Anular/Restaurar egreso** — POST `/egresos/anular/{id}`, `/egresos/restaurar/{id}` — `anular_egreso`,`restaurar_egreso` — (listado)

### Usuarios (Encargado) — `controllers/Usuarios.php`, `models/Usuario_model.php`, `views/usuarios/*` — `users`,`roles`
- **Listado** — `/usuarios` — `Usuarios::index` — `usuarios/listado_usuarios.php`
- **Nuevo** — `/usuarios/nuevo` + POST `/usuarios/guardar` — `nuevo`,`guardar` — `usuarios/formulario_usuario.php`
- **Editar** — `/usuarios/editar/{id}` + POST `/usuarios/actualizar/{id}` — `editar`,`actualizar` — `usuarios/formulario_usuario.php`
- **Eliminar/Restaurar** — POST `/usuarios/eliminar/{id}`, `/usuarios/restaurar/{id}` — `eliminar`,`restaurar` — (listado)

## 5) Atajos

- **Rutas:** `application/config/routes.php`
- **Controlador del módulo:** `application/controllers/<Nombre>.php`
- **Modelo:** `application/models/<Nombre>_model.php`
- **Vistas del módulo:** `application/views/<modulo>/*.php` (menú: `views/plantilla/menu_lateral.php`)
- **Helpers:** `application/helpers/barberia_helper.php` — **Assets:** `assets/` — **BD demo:** `database/barber_house.sql` — **Logs:** `application/logs/`
