# Pruebas de Barber House

Ejecutar desde la raíz con el PHP de XAMPP. No se necesitan librerías nuevas.

## 1. Sintaxis y referencias

```text
php tools/pruebas/verificar_codigo.php
```

Ejecuta `php -l` sobre el PHP propio (incluidas vistas y herramientas),
`node --check assets/js/interfaz_barberia.js`, y comprueba:

- archivo/clase de controladores y modelos según CI3;
- destinos de rutas actuales y alias anteriores;
- métodos llamados de modelos y vistas cargadas literalmente;
- ausencia de consultas fuera de modelos;
- instalador único sin borrado de base/tablas.

Node se utiliza únicamente para comprobar sintaxis de JavaScript; no es una
dependencia de ejecución de Barber House ni se instala ningún paquete.

## 2. Helpers y estructura sin MySQL

```text
php tools/pruebas/ejecutar.php
```

Es la verificación rápida existente, actualizada a los nombres en español.
Comprueba fechas/horas/importes, formatos, archivos, MVC y elementos del SQL.

## 3. Integración con MariaDB completamente aislada (Windows/XAMPP)

```text
php tools/pruebas/mariadb_aislada.php
```

Es la opción reproducible usada para esta reorganización:

1. Ubica `mysql_install_db.exe` y `mysqld.exe` en el XAMPP del PHP ejecutado.
2. Inicializa una carpeta nueva bajo `%TEMP%/opencode/barber_house_mariadb_*`.
3. Inicia MariaDB con `--no-defaults`, su propia carpeta de datos y puerto libre.
   No inicia un servicio ni usa la carpeta `mysql/data` de la instalación normal.
4. Ejecuta `integracion.php` pasando únicamente ese puerto de prueba.
5. El runner importa `database/barber_house.sql` en una operación, sustituyendo
   exactamente CREATE DATABASE/USE por `barber_house_prueba_<12 hexadecimales>`.
6. Abre dos servidores PHP locales para probar rutas, formularios y concurrencia.
7. Elimina la base temporal y cierra conexiones, servidores, sesiones, cookies y logs.
8. El lanzador detiene la instancia MariaDB y elimina su carpeta temporal.

El directorio `%TEMP%/opencode` debe existir. Todos los datos de la instancia son
descartables; no se selecciona ni se modifica la base principal `barber_house`.

### Casos comprobados

- Importación completa de 13 tablas con FK activas y datos iniciales.
- Acceso con ambos usuarios demo y rechazo de contraseña incorrecta/inactivos.
- Todas las rutas configuradas y los alias anteriores por HTTP.
- Carga real de modelos/vistas y enlaces/recursos generados por las pantallas.
- Acceso por rol, bloqueo administrativo por GET/POST y CSRF habilitado.
- Altas/cambios/archivo/restauración de clientes, peluqueros y usuarios.
- Registro/cambio de servicios y creación no duplicada de formas de pago.
- Registro/corrección/anulación/restauración de egresos.
- Turnos con varios servicios, jornada, cruces por intervalo y disponibilidad.
- Reasignación, llegada espontánea y liberación del horario al cancelar.
- Rollback de cambios de jornada que invalidarían una reserva futura.
- Snapshots de precio/duración y comisión; cobro único e ingreso automático.
- Corrección de cobro por Encargado conservando porcentaje histórico.
- Liquidación por período y resultado neto del local.
- Paginación de 25 filas conservando filtros.
- Cobro frente a cancelación simultáneos en dos servidores y sesiones distintas.
- Cierre de sesión y ausencia de errores CI3 en los logs de test.

### Runner directo con MariaDB ya disponible

```text
php tools/pruebas/integracion.php
```

Usa las credenciales locales y una base temporal nueva; no importa el SQL sobre
la base normal. Opcionalmente `BH_TEST_MYSQL_PORT` selecciona otro puerto.
El entorno test valida el nombre de base y guarda sesiones/logs fuera del
workspace. `enrutador.php` fija `CI_ENV=test` antes de cargar `index.php`, incluso
en Windows cuando cli-server no copia esa variable a `$_SERVER`.

## Límites y limpieza

- Conexión MySQL: 5 segundos; lectura MySQL: 10 segundos.
- HTTP: 3 segundos para conectar y 12 para responder.
- Servidores PHP: límite de ejecución de 20 segundos por petición.
- Runner: deadline de 150 segundos y límite de ejecución de 180 en Windows.
- Lanzador: inicialización acotada, espera del runner de hasta 190 segundos y
  límite de ejecución de 270 segundos.
- Limpieza registrada en `shutdown`; Windows cierra el árbol de procesos propio
  con su PID, sin cerrar el Apache/MySQL normal ni otros procesos del usuario.
- Los logs de un fallo se imprimen antes de eliminarlos para poder diagnosticarlo.

La suite HTTP usa `php -S` como servidor descartable de pruebas. El despliegue
normal sigue siendo **Apache + XAMPP** con `.htaccess`; estas pruebas no equivalen
a una inspección visual interactiva en el navegador ni a una prueba sobre datos reales.

Los resultados de la ejecución concreta están en `docs/INFORME_REORGANIZACION.md`.
