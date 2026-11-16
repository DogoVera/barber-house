# Informe de reorganización y documentación

Fecha: **29/10/2026**. Proyecto: Barber House — Gestión de Turnos y Contabilidad.

## 1. Resultado y alcance

Se reorganizaron **46 archivos**: nueve controladores, siete modelos, 27 vistas,
un helper y dos recursos CSS/JS. Las 16 clases de módulos/modelos coinciden con
sus nuevos archivos. Se incorporó una vista parcial de errores reutilizada por
los formularios existentes; no es una pantalla ni una funcionalidad nueva.

Se mantienen CodeIgniter **3.1.13**, PHP, MVC, MySQL/MariaDB, Apache/XAMPP,
las URL públicas, las reglas de negocio y las reglas CSS originales. No se
ejecutaron operaciones con Git, no se generó ZIP y no se modificó la base principal.
El núcleo `system/` permanece sin cambios.

## 2. Controladores renombrados

Todos dentro de `application/controllers/`; el cambio de clase es igual al del
archivo, quitando `.php`.

| Anterior | Actual |
|---|---|
| `Auth.php` | `Autenticacion.php` |
| `Dashboard.php` | `Panel_principal.php` |
| `Clients.php` | `Clientes.php` |
| `Barbers.php` | `Peluqueros.php` |
| `Services.php` | `Servicios.php` |
| `Appointments.php` | `Turnos.php` |
| `Payments.php` | `Cobros.php` |
| `Accounting.php` | `Contabilidad.php` |
| `Users.php` | `Usuarios.php` |

## 3. Modelos renombrados

Todos dentro de `application/models/`; clase y archivo también coinciden.

| Anterior | Actual |
|---|---|
| `Client_model.php` | `Cliente_model.php` |
| `Barber_model.php` | `Peluquero_model.php` |
| `Service_model.php` | `Servicio_model.php` |
| `Appointment_model.php` | `Turno_model.php` |
| `Payment_model.php` | `Cobro_model.php` |
| `Expense_model.php` | `Egreso_model.php` |
| `User_model.php` | `Usuario_model.php` |

## 4. Vistas y recursos renombrados

Las primeras 27 filas son relativas a `application/views/`.

| Anterior | Actual |
|---|---|
| `auth/login.php` | `autenticacion/inicio_sesion.php` |
| `dashboard/index.php` | `panel/resumen_jornada.php` |
| `clients/index.php` | `clientes/listado_clientes.php` |
| `clients/form.php` | `clientes/formulario_cliente.php` |
| `clients/show.php` | `clientes/detalle_cliente.php` |
| `barbers/index.php` | `peluqueros/listado_peluqueros.php` |
| `barbers/form.php` | `peluqueros/formulario_peluquero.php` |
| `barbers/availability.php` | `peluqueros/disponibilidad_peluquero.php` |
| `barbers/reassign.php` | `peluqueros/reasignar_turnos.php` |
| `services/index.php` | `servicios/listado_servicios.php` |
| `services/form.php` | `servicios/formulario_servicio.php` |
| `appointments/index.php` | `turnos/listado_turnos.php` |
| `appointments/form.php` | `turnos/formulario_turno.php` |
| `appointments/show.php` | `turnos/detalle_turno.php` |
| `payments/index.php` | `cobros/listado_cobros.php` |
| `payments/form.php` | `cobros/formulario_cobro.php` |
| `payments/edit.php` | `cobros/editar_cobro.php` |
| `payments/methods.php` | `cobros/formas_pago.php` |
| `accounting/index.php` | `contabilidad/resumen_contabilidad.php` |
| `accounting/expenses.php` | `contabilidad/listado_egresos.php` |
| `accounting/expense_form.php` | `contabilidad/formulario_egreso.php` |
| `users/index.php` | `usuarios/listado_usuarios.php` |
| `users/form.php` | `usuarios/formulario_usuario.php` |
| `layout/header.php` | `plantilla/cabecera.php` |
| `layout/sidebar.php` | `plantilla/menu_lateral.php` |
| `layout/footer.php` | `plantilla/pie.php` |
| `partials/pagination.php` | `compartidas/paginacion.php` |
| `application/helpers/app_helper.php` | `application/helpers/barberia_helper.php` |
| `assets/css/app.css` | `assets/css/estilos_barberia.css` |
| `assets/js/app.js` | `assets/js/interfaz_barberia.js` |

Se retiraron únicamente las carpetas antiguas que quedaron vacías. Las vistas
`errors/html/error_*.php` conservan sus nombres técnicos de CI3 y ahora están
comentadas y distribuidas en líneas legibles. El logotipo conserva su archivo.

## 5. Métodos renombrados

### Controladores

`index()` conserva la convención de CI3. En Clientes, Peluqueros, Servicios,
Turnos, Cobros y Usuarios se aplican, según las operaciones reales de cada uno:

| Anterior | Actual |
|---|---|
| `create` | `nuevo` |
| `store` | `guardar` |
| `edit` | `editar` |
| `update` | `actualizar` |
| `show` | `ver` |
| `delete` | `eliminar` |
| `restore` | `restaurar` |

Operaciones específicas y métodos privados:

| Clase | Anterior → actual |
|---|---|
| Autenticacion | `login` → `iniciar_sesion`; `logout` → `cerrar_sesion` |
| Clientes | `validate` → `validar_datos`; `data` → `datos_cliente` |
| Peluqueros | `availability` → `disponibilidad`; `reassign` → `reasignar`; `render_form` → `mostrar_formulario`; `schedule_rows` → `filas_horarios`; `validate` → `validar_datos`; `data` → `datos_peluquero`; `respond_to_save` → `responder_guardado` |
| Servicios | `validate` → `validar_datos`; `data` → `datos_servicio` |
| Turnos | `walkin` → `sin_reserva`; `availability` → `disponibilidad`; `status` → `cambiar_estado`; `render_form` → `mostrar_formulario`; `validate_form` → `validar_formulario`; `save` → `guardar_formulario` |
| Cobros | `methods` → `formas_pago`; `store_method` → `guardar_forma_pago` |
| Contabilidad | `selected_range` → `periodo_seleccionado`; `expenses` → `egresos`; `expense_input` → `datos_egreso`; `store_expense` → `guardar_egreso`; `edit_expense` → `editar_egreso`; `update_expense` → `actualizar_egreso`; `void_expense` → `anular_egreso`; `restore_expense` → `restaurar_egreso` |
| Usuarios | `validate` → `validar_datos`; `data` → `datos_usuario` |

### Modelos

| Clase | Anterior → actual |
|---|---|
| Cliente_model | `search` → `buscar_clientes`; `count_search` → `contar_clientes`; `find` → `buscar_cliente`; `find_archived` → `buscar_archivado`; `duplicate` → `buscar_duplicado`; `create` → `registrar_cliente`; `update_client` → `actualizar_cliente`; `archive` → `archivar_cliente`; `restore` → `restaurar_cliente`; `has_future_reservations` → `tiene_reservas_futuras`; `history` → `obtener_historial` |
| Peluquero_model | `all` → `obtener_peluqueros`; `find` → `buscar_peluquero`; `is_archived` → `esta_archivado`; `archive` → `archivar_peluquero`; `restore` → `restaurar_peluquero`; `schedules` → `consultar_horarios`; `service_ids` → `obtener_servicios`; `supports_services` → `realiza_servicios`; `within_schedule` → `respeta_horario`; `future_appointment_is_valid` → `reserva_futura_valida`; `save` → `guardar_peluquero`; `future_appointments` → `obtener_turnos_futuros` |
| Servicio_model | `all` → `obtener_servicios`; `find` → `buscar_servicio`; `get_many` → `obtener_activos`; `has_future_appointments` → `tiene_turnos_futuros`; `is_archived` → `esta_archivado`; `create` → `registrar_servicio`; `update_service` → `actualizar_servicio`; `archive` → `archivar_servicio`; `restore` → `restaurar_servicio` |
| Turno_model | `for_date` → `obtener_turnos`; `count_for_date` → `contar_turnos`; `find` → `buscar_turno`; `services` → `obtener_servicios`; `has_payment` → `tiene_cobro`; `overlap_exists` → `hay_superposicion`; `slot_validation` → `validar_horario`; `available_slots` → `horarios_disponibles`; `save_appointment` → `guardar_turno`; `change_status` → `cambiar_estado`; `reassign` → `reasignar_turno` |
| Cobro_model | `methods` → `obtener_formas_pago`; `method_name_exists` → `existe_forma_pago`; `create_method` → `registrar_forma_pago`; `filter_list` → `filtros_listado`; `consulta_between` → `consulta_cobros`; `find` → `buscar_cobro`; `for_appointment` → `por_turno`; `void_payment` → `anular_cobro`; `restore_payment` → `restaurar_cobro`; `create_for_appointment` → `registrar_cobro_turno`; `active_method` → `forma_pago_activa`; `update_payment` → `actualizar_cobro`; `total_between` → `total_ingresos`; `commissions_between` → `total_comisiones`; `liquidation_between` → `calcular_liquidacion`; `between` → `obtener_cobros`; `count_between` → `contar_cobros` |
| Egreso_model | `categories` → `obtener_categorias`; `valid_category` → `categoria_valida`; `all_between` → `obtener_egresos`; `count_all_between` → `contar_egresos`; `find` → `buscar_egreso`; `create` → `registrar_egreso`; `update_expense` → `actualizar_egreso`; `void_expense` → `anular_egreso`; `restore_expense` → `restaurar_egreso`; `total_between` → `total_egresos` |
| Usuario_model | `find_active_by_username` → `buscar_activo_por_usuario`; `all` → `obtener_usuarios`; `roles` → `obtener_roles`; `find` → `buscar_usuario`; `username_exists` → `existe_usuario`; `is_archived` → `esta_archivado`; `touch_last_login` → `registrar_acceso`; `archive` → `archivar_usuario`; `restore` → `restaurar_usuario`; `create` → `registrar_usuario`; `update_user` → `actualizar_usuario` |

JavaScript: `changeTheme` → `cambiarTema`, `calculateServices` →
`calcularServicios`, `loadAvailability` → `cargarDisponibilidad`, incluidas las
referencias de sus eventos. Se conservan las funciones públicas del helper y
los métodos compartidos de MY_Controller; tienen documentación en español.

## 6. Referencias y legibilidad

- Cargas de modelos y llamadas internas de controladores, modelos y MY_Controller.
- Cargas de vistas de cada módulo, plantilla, errores compartidos y paginación.
- Rutas a clases/métodos nuevos y alias de URL anteriores, sin redirecciones
  intermedias que pudieran perder datos POST.
- Selección visual del módulo activo del menú según clase/método renombrados.
- Carga automática del helper y enlaces a CSS/JS locales con versión de caché.
- El AJAX conserva `turnos/disponibilidad` y las mismas claves de entrada/JSON.
- Pruebas y documentación actualizadas a las rutas físicas nuevas.

Se documentaron responsabilidades, modelos, tablas, operaciones y permisos;
los métodos explican parámetros/resultados y los errores importantes. Se
identifican validación de intervalos, históricos, transacciones y bloqueos.
Las vistas tienen encabezado y comentarios de sus bloques. Se expandieron las
pantallas comprimidas de acceso, listados, detalle del turno y corrección de
egresos, además del arranque PHP y la configuración de conexión.

Se reutilizó el listado de estados ya existente y se extrajo únicamente la
presentación repetida de errores. No se alteraron cálculos ni validaciones del
negocio. No se renombraron tablas, columnas, campos de formulario ni claves JSON.

## 7. SQL único

`database/barber_house.sql` es el único SQL de instalación. Crea una base nueva,
13 tablas, claves primarias/foráneas, índices y datos iniciales en orden válido.
Incluye comentarios en español por tabla y sobre relaciones, restricciones,
históricos, bajas/anulaciones, datos demo y credenciales.

Se retiraron `DROP DATABASE` y la desactivación innecesaria de claves foráneas.
La importación falla si la base ya existe; no es un actualizador de datos reales.

Se revisó `upgrade_crud.sql`: sus cuatro `deleted_at` (users, clients, barbers,
services), `payments.voided_at`, `payments.voided_by` y su FK a users **ya estaban
incluidos**. No se duplicó ninguna columna ni relación. Se retiró el script
redundante después de verificarlo. No hubo cambio estructural del esquema.

## 8. Pruebas y resultados

Los resultados finales se registran también en `ESTADO_IMPLEMENTACION_V1.md`.
Los comandos y cobertura se explican en `tools/pruebas/README.md`.

| Comprobación ejecutada | Resultado |
|---|---|
| `php tools/pruebas/verificar_codigo.php` | 65 archivos PHP con `php -l`; JavaScript con `node --check`; 615 verificaciones estáticas sin fallos |
| Rutas/referencias estáticas | 131 destinos (incluido el predeterminado), 134 llamadas a modelos y 51 cargas literales de vistas válidas |
| `php tools/pruebas/ejecutar.php` | 27 verificaciones rápidas, 0 fallos |
| `php tools/pruebas/mariadb_aislada.php` | 473 verificaciones de integración, 0 fallos; MariaDB 10.4.32 de XAMPP |
| Importación SQL | Una operación completa en base temporal, 13 tablas, FK activas y datos iniciales válidos |
| Usuarios demo | Acceso HTTP correcto de encargado y recepcion; se rechazan clave errónea y usuario inactivo |
| Navegación | 130 rutas públicas/alias y 54 enlaces/recursos generados comprobados por HTTP |
| Negocio | Formularios, disponibilidad, snapshots, reasignación, cobros/comisiones, liquidación, egresos, permisos, CSRF y paginación comprobados |
| Concurrencia | Cobro y cancelación simultáneos en dos servidores/sesiones; invariante conservado |
| MVC | Sin consultas de BD en controladores ni vistas; transacciones/bloqueos conservados en modelos |
| Limpieza | Base, servidores PHP, instancia MariaDB, cookies, sesiones, logs y carpeta de datos temporales eliminados |

La última base importada fue `barber_house_prueba_ef02be8d1735`, eliminada al
terminar. Una consulta posterior de procesos no encontró servidores PHP/MariaDB
remanentes. Los logs y sesiones previos del workspace se conservaron.

### Incidencias resueltas durante la verificación

1. MariaDB normal estaba detenido. Se preparó una instancia totalmente aislada
   con ejecutables de XAMPP, carpeta temporal y puerto libre, sin iniciar servicios.
2. En Windows, cli-server no propagaba CI_ENV a `$_SERVER`; se fijó explícitamente
   en el enrutador de test antes del front controller. Los dos intentos fallidos
   de conexión al servidor normal fueron rechazados: no hubo conexión ni consulta
   sobre la base principal. Se retiraron solo sus líneas de log generadas por test.
3. Se corrigieron las expectativas del runner sobre redirecciones CI3: HTTP/1.1
   utiliza 307 para GET y 303 para POST, además del 302 de HTTP/1.0.

La suite histórica de 172 integraciones citada en el baseline **no estaba en
este workspace**. La herramienta disponible era la verificación rápida; se
incorporó una suite reproducible para esta reorganización. Los conteos históricos
no se presentan como pruebas ejecutadas en esta sesión.

## 9. Pendientes y límites

No se detectaron regresiones funcionales en los casos ejecutados. La tarea no
incluyó una nueva auditoría exhaustiva requisito por requisito de los 71 puntos.
No se realizó inspección visual interactiva ni prueba del despliegue Apache
contra información real; las pruebas HTTP utilizaron servidores PHP descartables.
El diseño se conserva por mantener las reglas CSS, clases, campos y acciones.
Para el uso normal se inicia Apache/MySQL desde XAMPP como indica el README.

## 10. Documento para la evaluación

`docs/ESTRUCTURA_DEL_PROYECTO.md` contiene el árbol de carpetas, tablas de todos
los archivos importantes y seis recorridos reales: cliente, turno,
disponibilidad, cobro, liquidación y guardado en MySQL. Es el punto de entrada
recomendado para localizar qué archivo modificar manualmente.
