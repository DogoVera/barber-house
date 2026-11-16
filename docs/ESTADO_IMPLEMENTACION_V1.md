# Estado de implementación - Barber House V1.0.0

## Alcance

El alcance de la V1 está definido por los **71 requerimientos** documentados en
`REQUERIMIENTOS_V1.md`, considerando la decisión técnica vigente que reemplaza
CodeIgniter 4 por **CodeIgniter 3.1.13**. La reorganización del 29/10/2026 conserva
ese alcance y comprueba regresiones; no constituye una nueva auditoría exhaustiva
de cumplimiento requisito por requisito.

## Arquitectura

- **Controllers:** reciben acciones, validan permisos/datos y coordinan el caso de uso.
- **Models:** concentran consultas MySQL, transacciones y reglas relacionadas con persistencia.
- **Views:** muestran formularios, tablas y resultados.
- **JavaScript:** mejora la experiencia (tema, sidebar, estimados y consulta de disponibilidad), pero las reglas críticas se vuelven a validar en backend.

## Reglas especialmente protegidas

- Superposición real de turnos por intervalo de inicio/fin.
- Días y horarios de trabajo del peluquero.
- Varios servicios por turno.
- Snapshots históricos de precio y duración.
- Cobro único por atención.
- Ingreso generado a partir del cobro.
- Snapshot del porcentaje y comisión del peluquero.
- Concurrencia entre cobro y cambio de estado.
- Cambios de disponibilidad que podrían invalidar reservas futuras.
- Permisos de Recepcionista y Encargado comprobados en backend.
- Paginación de 25 registros en listados históricos.

## Verificación ejecutada después de la reorganización (29/10/2026)

Se ejecutaron los siguientes comandos desde la raíz:

| Comando | Resultado |
|---|---|
| `php tools/pruebas/verificar_codigo.php` | 615 verificaciones estáticas, 0 fallos |
| `php tools/pruebas/ejecutar.php` | 27 verificaciones rápidas, 0 fallos |
| `php tools/pruebas/mariadb_aislada.php` | 473 verificaciones de integración, 0 fallos |

La verificación estática incluye **65 archivos PHP** con `php -l`, sintaxis de
`assets/js/interfaz_barberia.js` con `node --check`, 131 destinos de rutas,
134 llamadas a modelos y 51 cargas literales de vistas. Se comprobaron nombres
de clases/archivos y separación MVC; también se buscaron consultas directamente
en controladores/vistas, sin coincidencias.

La integración se realizó con **MariaDB 10.4.32 de XAMPP**, utilizando instancia,
carpeta de datos y puerto independientes. El último instalador se importó en
`barber_house_prueba_ef02be8d1735`: 13 tablas, FK activas, datos iniciales y acceso
HTTP correcto de ambos usuarios demo. Esa base fue eliminada al finalizar.

Se probaron 130 rutas actuales/alias por HTTP, la entrada raíz y 54 enlaces o
recursos generados. Se verificaron formularios, ambos roles, CSRF, agenda,
superposición, disponibilidad, cambios atómicos de jornada, snapshots,
reasignación, cobros únicos, comisiones, liquidación, egresos y paginación.
Se ejecutó una carrera real entre cobro y cancelación en dos servidores y
sesiones distintos, conservando el invariante de turno cobrado/atendido.

El runner y el lanzador informaron la limpieza de la base, archivos temporales,
servidores PHP y MariaDB. La comprobación posterior del sistema no encontró
procesos `php.exe`, `mysqld.exe` ni `mariadbd.exe` remanentes de las pruebas.
Las sesiones y logs anteriores del workspace se conservaron.

La ejecución normal sigue siendo **Apache/XAMPP**. No se modificó la base real,
no se ejecutaron operaciones con Git y no se generó ZIP. La prueba HTTP aislada
no reemplaza una inspección visual interactiva del despliegue Apache.

## Baseline del cierre V1

El cierre V1 previo registró 30 verificaciones de helpers + 172 de integración =
**202 verificaciones, 0 fallos**, según `AGENTS.md`. Es una referencia histórica:
la suite de 172 integraciones no estaba incluida en este workspace. Los
resultados ejecutados ahora son los de la tabla anterior y no se equiparan
artificialmente con ese conteo histórico.

Consultar `docs/ESTRUCTURA_DEL_PROYECTO.md` para estudiar el código y
`docs/INFORME_REORGANIZACION.md` para el detalle de renombres y cambios.
