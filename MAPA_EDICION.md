# MAPA DE EDICIÓN - BARBER HOUSE
(para leer rápido en el parcial)

NO se movieron carpetas: en CodeIgniter 3 deben quedar así
o se rompe todo (rutas, controladores, vistas).
La idea es: sabés la pantalla -> abrís estos archivos.


==================================================
1) SI TE PIDEN... ABRÍ ESTO
==================================================

BOTÓN (solo ir a otra página)
  - Vista: application/views/<modulo>/<pantalla>.php
  - Copiar estilo: <a class="btn" href="<?= site_url('ruta') ?>">Texto</a>

BOTÓN QUE HACE ALGO (guardar / cambiar estado / borrar)
  - Vista:      application/views/<modulo>/<pantalla>.php
  - Ruta:       application/config/routes.php
  - Controlador: application/controllers/<Modulo>.php  (método nuevo)
  - Modelo:     application/models/<Modulo>_model.php  (si toca BD)
  - Copiar estilo: <?= form_open('ruta/'.$id) ?><button class="btn" type="submit">Texto</button><?= form_close() ?>

LISTA DESPLEGABLE (dropdown) EN UN FORMULARIO
  - Vista:       application/views/<modulo>/formulario_*.php  (agregar <select name="campo">)
  - Controlador: application/controllers/<Modulo>.php  (en guardar/actualizar leer $this->posted('campo'))
  - Modelo:      application/models/<Modulo>_model.php  (agregar campo en insert/update si se guarda)
  - BD:          solo si el campo no existe (ver sección 3)
  - Importante:  name="campo"  =  $this->posted('campo')  =  columna en BD

LISTA DESPLEGABLE COMO FILTRO EN UN LISTADO
  - Vista:       application/views/<modulo>/listado_*.php  (dentro del <form method="get">)
  - Controlador: application/controllers/<Modulo>.php  (método index: leer GET, pasar a vista y a filtros_paginacion)
  - Modelo:      application/models/<Modulo>_model.php  (agregar where en la consulta y en el contar)

COLUMNA NUEVA EN UNA TABLA (la tabla HTML del listado)
  - Vista:  application/views/<modulo>/listado_*.php
            - agregar <th> en el encabezado
            - agregar <td> en cada fila
            - ajustar colspan de "No se encontraron..."
  - Modelo: application/models/<Modulo>_model.php  (si el dato no viene, agregarlo al select/join)

FILA NUEVA EN UNA TABLA (ej. fila de total)
  - Vista:  application/views/<modulo>/listado_*.php
            - agregar <tr>...</tr> en el <tbody> con el cálculo

AGREGAR COLUMNA EN LA BASE DE DATOS (y usarla)
  - BD:          ejecutar SQL (phpMyAdmin o consola)
  - Modelo:      agregar el campo en select / insert / update
  - Controlador: leerlo y pasarlo al modelo/vista
  - Vista:       input/select para cargarlo y/o columna para mostrarlo

AGREGAR TABLA EN LA BASE DE DATOS (+ registros)
  - BD:          CREATE TABLE ...  +  INSERT INTO ...
  - (si la usa la app) crear modelo + controlador + vistas + ruta


==================================================
2) CÓMO LLEGAR A CUALQUIER PANTALLA
==================================================

1. Mirá la URL en el navegador (ej. /cobros, /turnos/nuevo)
2. Buscá esa URL en:  application/config/routes.php
   -> te dice: Controlador :: método
3. Abrí el controlador: application/controllers/<Controlador>.php
   -> método = lo que hace la pantalla (carga datos, valida, guarda)
4. El controlador llama al modelo: application/models/<...>_model.php
   -> ahí están las consultas a la BD (SELECT/INSERT/UPDATE)
5. El controlador muestra la vista: application/views/<carpeta>/<archivo>.php
   -> ahí está el HTML (formularios, tablas, botones)

ATAJO por tipo de archivo:
  Rutas (todas las URLs):     application/config/routes.php
  Controladores (lógica):     application/controllers/
  Modelos (BD):               application/models/
  Vistas (pantallas/HTML):    application/views/
  Menú lateral (sidebar):     application/views/plantilla/menu_lateral.php
  Layout común:               application/views/plantilla/cabecera.php  y  pie.php
  Errores de formulario:      application/views/compartidas/errores_formulario.php
  Paginación:                 application/views/compartidas/paginacion.php
  Helpers (h, money, etc):    application/helpers/barberia_helper.php
  Conexión BD:                application/config/database.php
  SQL instalación/datos demo: database/barber_house.sql
  Logs de errores:            application/logs/log-YYYY-MM-DD.php


==================================================
3) BASE DE DATOS (rápido para la demo en tu PC)
==================================================

Agregar columna:
  ALTER TABLE payments ADD COLUMN mi_campo VARCHAR(50) NULL;

Agregar tabla y registros:
  CREATE TABLE ejemplo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
  ) ENGINE=InnoDB;

  INSERT INTO ejemplo (nombre, active) VALUES ('Uno', 1), ('Dos', 1);

Después de tocar la BD: actualizar modelo (select/insert/update) y vista.
Regla del proyecto: en serio iría a database/updates/ y a database/barber_house.sql,
y nunca sobre una base real con datos. Para el parcial local, ALTER directo suele alcanzar.


==================================================
4) PANTALLAS: DÓNDE ESTÁ CADA UNA
==================================================

--------------------------------------------------
INICIO Y ACCESO
--------------------------------------------------
LOGIN  (url /login)
  Controlador: application/controllers/Autenticacion.php  -> iniciar_sesion
  Modelo:      application/models/Usuario_model.php
  Vista:       application/views/autenticacion/inicio_sesion.php
  BD:          users, roles

INICIO / RESUMEN  (url /inicio)
  Controlador: application/controllers/Panel_principal.php  -> index
  Vista:       application/views/panel/resumen_jornada.php

MENÚ LATERAL (sidebar, aparece en todas)
  Vista:       application/views/plantilla/menu_lateral.php


--------------------------------------------------
CLIENTES  (todos los roles)
  Controlador: application/controllers/Clientes.php
  Modelo:      application/models/Cliente_model.php
  BD:          clients  (campos: first_name, last_name, phone, tipo, notes)
--------------------------------------------------
Listado / búsqueda        /clientes
  -> Clientes::index      vista: views/clientes/listado_clientes.php

Nuevo cliente             /clientes/nuevo  (guardar: POST /clientes/guardar)
  -> nuevo, guardar       vista: views/clientes/formulario_cliente.php

Editar cliente            /clientes/editar/{id}  (guardar: POST /clientes/actualizar/{id})
  -> editar, actualizar   vista: views/clientes/formulario_cliente.php

Detalle / historial       /clientes/ver/{id}
  -> ver                  vista: views/clientes/detalle_cliente.php

Eliminar / Restaurar      POST /clientes/eliminar/{id}  y  /clientes/restaurar/{id}
  -> eliminar, restaurar  (botones en listado/detalle)

Nota: el dropdown "Tipo" (VIP/Regular/Nuevo) ya está en formulario_cliente.php.


--------------------------------------------------
AGENDA / TURNOS  (todos los roles)
  Controlador: application/controllers/Turnos.php
  Modelo:      application/models/Turno_model.php  (+ Cliente, Peluquero, Servicio)
  BD:          appointments, appointment_services
--------------------------------------------------
Agenda / listado          /turnos
  -> Turnos::index        vista: views/turnos/listado_turnos.php

Nuevo turno               /turnos/nuevo  (guardar: POST /turnos/guardar)
  -> nuevo, guardar       vista: views/turnos/formulario_turno.php

Atención sin reserva      /turnos/sin-reserva  (guardar: POST /turnos/guardar)
  -> sin_reserva, guardar vista: views/turnos/formulario_turno.php

Editar turno              /turnos/editar/{id}  (guardar: POST /turnos/actualizar/{id})
  -> editar, actualizar   vista: views/turnos/formulario_turno.php

Detalle del turno         /turnos/ver/{id}
  -> ver                  vista: views/turnos/detalle_turno.php

Cambiar estado / Cancelar POST /turnos/estado/{id}  y  /turnos/eliminar/{id}
  -> cambiar_estado, eliminar  (botones en detalle)

Disponibilidad (AJAX)     /turnos/disponibilidad
  -> disponibilidad       (devuelve JSON)


--------------------------------------------------
COBROS  (todos; editar/anular/restaurar = solo Encargado)
  Controlador: application/controllers/Cobros.php
  Modelo:      application/models/Cobro_model.php  (+ Turno_model)
  BD:          payments, payment_methods
--------------------------------------------------
Listado de cobros         /cobros
  -> Cobros::index        vista: views/cobros/listado_cobros.php
  Filtros GET: q, from, to, archived
  Tabla HTML: Fecha | Cliente | Peluquero | Forma | Importe | Acciones
  Nota: el botón "+ Registrar cobro" del listado lleva a /turnos

Registrar cobro           /cobros/nuevo/{id_turno}  (guardar: POST /cobros/guardar)
  -> nuevo, guardar       vista: views/cobros/formulario_cobro.php
  Cómo se llega: Turnos > ver turno > "Marcar atendido / cobrar"

Editar cobro              /cobros/editar/{id}  (guardar: POST /cobros/actualizar/{id})
  -> editar, actualizar   vista: views/cobros/editar_cobro.php

Anular / Restaurar        POST /cobros/eliminar/{id}  y  /cobros/restaurar/{id}
  -> eliminar, restaurar  (botones en listado)

Formas de pago            /formas-pago  (guardar: POST /formas-pago/guardar)
  -> formas_pago, guardar_forma_pago  vista: views/cobros/formas_pago.php


--------------------------------------------------
PELUQUEROS  (solo Encargado)
  Controlador: application/controllers/Peluqueros.php
  Modelo:      application/models/Peluquero_model.php
  BD:          barbers, barber_schedules, barber_services
--------------------------------------------------
Listado                   /peluqueros
  -> Peluqueros::index    vista: views/peluqueros/listado_peluqueros.php

Nuevo peluquero           /peluqueros/nuevo  (guardar: POST /peluqueros/guardar)
  -> nuevo, guardar       vista: views/peluqueros/formulario_peluquero.php

Editar peluquero          /peluqueros/editar/{id}  (guardar: POST /peluqueros/actualizar/{id})
  -> editar, actualizar   vista: views/peluqueros/formulario_peluquero.php

Activar / Desactivar      POST /peluqueros/estado/{id}
  -> cambiar_estado       (botón en listado; usa validación de turnos futuros)

Disponibilidad            /peluqueros/disponibilidad/{id}
  -> disponibilidad       vista: views/peluqueros/disponibilidad_peluquero.php

Reasignar turnos          /peluqueros/reasignar/{id}
  -> reasignar            vista: views/peluqueros/reasignar_turnos.php

Eliminar / Restaurar      POST /peluqueros/eliminar/{id}  y  /peluqueros/restaurar/{id}
  -> eliminar, restaurar  (botones en listado)


--------------------------------------------------
SERVICIOS  (solo Encargado)
  Controlador: application/controllers/Servicios.php
  Modelo:      application/models/Servicio_model.php
  BD:          services
--------------------------------------------------
Listado                   /servicios
  -> Servicios::index     vista: views/servicios/listado_servicios.php

Nuevo servicio            /servicios/nuevo  (guardar: POST /servicios/guardar)
  -> nuevo, guardar       vista: views/servicios/formulario_servicio.php

Editar servicio           /servicios/editar/{id}  (guardar: POST /servicios/actualizar/{id})
  -> editar, actualizar   vista: views/servicios/formulario_servicio.php

Eliminar / Restaurar      POST /servicios/eliminar/{id}  y  /servicios/restaurar/{id}
  -> eliminar, restaurar  (botones en listado)


--------------------------------------------------
CONTABILIDAD Y EGRESOS  (solo Encargado)
  Controlador: application/controllers/Contabilidad.php
  Modelos:     application/models/Cobro_model.php, application/models/Egreso_model.php
--------------------------------------------------
Resumen contable          /contabilidad
  -> Contabilidad::index  vista: views/contabilidad/resumen_contabilidad.php
  BD: payments, expenses

Egresos (listado + alta)  /egresos  (guardar: POST /egresos/guardar)
  -> egresos, guardar_egreso  vista: views/contabilidad/listado_egresos.php
  BD: expenses, expense_categories

Editar egreso             /egresos/editar/{id}  (guardar: POST /egresos/actualizar/{id})
  -> editar_egreso, actualizar_egreso  vista: views/contabilidad/formulario_egreso.php

Anular / Restaurar egreso POST /egresos/anular/{id}  y  /egresos/restaurar/{id}
  -> anular_egreso, restaurar_egreso  (botones en listado)


--------------------------------------------------
USUARIOS  (solo Encargado)
  Controlador: application/controllers/Usuarios.php
  Modelo:      application/models/Usuario_model.php
  BD:          users, roles
--------------------------------------------------
Listado                   /usuarios
  -> Usuarios::index      vista: views/usuarios/listado_usuarios.php

Nuevo usuario             /usuarios/nuevo  (guardar: POST /usuarios/guardar)
  -> nuevo, guardar       vista: views/usuarios/formulario_usuario.php

Editar usuario            /usuarios/editar/{id}  (guardar: POST /usuarios/actualizar/{id})
  -> editar, actualizar   vista: views/usuarios/formulario_usuario.php

Eliminar / Restaurar      POST /usuarios/eliminar/{id}  y  /usuarios/restaurar/{id}
  -> eliminar, restaurar  (botones en listado)


==================================================
5) RECORDATORIOS RÁPIDOS
==================================================
- "name" del input/select  =  lo que lee el controlador ($this->posted)  =  campo en BD/modelo
- Controlador NO lleva SQL. SQL/consultas van en el Modelo.
- Para ver un ejemplo real de botón con acción: Peluqueros "Activar/Desactivar"
  (routes.php -> Peluqueros::cambiar_estado -> vista listado_peluqueros.php)
- Usuarios demo: recepcion / 123456  y  encargado / 123456
- Si algo falla: revisar application/logs/log-YYYY-MM-DD.php

