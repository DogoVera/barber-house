# Barber House V1.0.0

Sistema académico **Gestión de Turnos y Contabilidad** para una peluquería.

## Tecnologías

- PHP.
- CodeIgniter **3.1.13**.
- MySQL / MariaDB.
- XAMPP (Apache + MariaDB/MySQL).
- HTML, CSS y JavaScript.
- Arquitectura **MVC (Modelo - Vista - Controlador)**.

## Cómo está organizado

```text
application/
├── controllers/   # Reciben acciones, validan y coordinan el sistema
├── models/        # Consultan y modifican MySQL
├── views/         # Interfaz que ve el usuario
├── helpers/       # Funciones pequeñas reutilizables
├── core/          # Controlador base y permisos comunes
└── config/        # Rutas, base de datos y configuración

assets/            # CSS, JavaScript e imágenes
database/          # SQL para crear la base desde cero
docs/              # Relevamiento, requerimientos y contexto del proyecto
```

La idea del código es que sea fácil de estudiar: controladores sin SQL, modelos para la persistencia y vistas dedicadas a mostrar información.

## Instalación en XAMPP

1. Copiar la carpeta del proyecto dentro de `C:\xampp\htdocs\`.
2. Iniciar **Apache** y **MySQL/MariaDB** desde XAMPP.
3. Abrir phpMyAdmin.
4. Para una instalación nueva donde todavía no exista `barber_house`, importar el único archivo `database/barber_house.sql`.
5. Si tu MySQL usa una contraseña distinta, ajustar `application/config/database.php`.
6. Abrir en el navegador la URL correspondiente a la carpeta. Por ejemplo:

```text
http://localhost/BarberHome/
```

`base_url` se calcula automáticamente a partir de la ubicación del proyecto, por lo que no es necesario fijar una ruta única.

## Usuarios de demostración

| Perfil | Usuario | Contraseña |
|---|---|---|
| Encargado | `encargado` | `123456` |
| Recepcionista | `recepcion` | `123456` |

También están en `DATOS_DE_ACCESO.txt`.

## Funcionalidades principales

- Login y permisos por perfil.
- Clientes e historial.
- Peluqueros, horarios, servicios y porcentajes.
- Agenda y turnos.
- Consulta de espacios disponibles.
- Varios servicios por turno.
- Validación de horarios y superposición.
- Atención sin turno previo.
- Cobros e ingreso automático.
- Formas de pago.
- Egresos y contabilidad por período.
- Producción y comisiones por peluquero.
- Usuarios.
- Tema claro / oscuro y diseño responsive.
- Paginación de 25 registros en listados históricos.

## Base de datos

La instalación limpia está en:

```text
database/barber_house.sql
```

El archivo crea la base nueva, sus 13 tablas, relaciones y datos de demostración
en una sola importación. No contiene `DROP DATABASE` y falla si la base ya existe.
No es un actualizador de instalaciones con información real. El antiguo
`upgrade_crud.sql` se retiró porque todos sus campos ya están incluidos.

## Guía para estudiar el código y pruebas

- [`docs/ESTRUCTURA_DEL_PROYECTO.md`](docs/ESTRUCTURA_DEL_PROYECTO.md): tabla de archivos y seis recorridos reales, paso a paso.
- [`docs/INFORME_REORGANIZACION.md`](docs/INFORME_REORGANIZACION.md): nombres anteriores/nuevos y resultados de la reorganización.
- [`tools/pruebas/README.md`](tools/pruebas/README.md): sintaxis, helpers, rutas e integración con MariaDB aislada.

Los controladores/modelos y carpetas de vistas usan nombres en español. Las URL
públicas siguen siendo las mismas; `application/config/routes.php` conserva
también alias para las antiguas URL directas de controladores en inglés.

## Contexto y requerimientos

La documentación que guía el proyecto se encuentra en `docs/`, especialmente:

- `REQUERIMIENTOS_V1.md` — 71 requerimientos de la V1.
- `CONTEXTO_PROYECTO.md` — alcance y relevamiento resumido.
- `DECISIONES_TECNICAS.md` — decisiones vigentes.
- `MATRIZ_TRAZABILIDAD.md` — relación entre requerimientos y módulos.

La decisión técnica vigente es **CodeIgniter 3.1.13 + MySQL/MariaDB + XAMPP**. No se utiliza SQLite.

## Seguridad básica

- Contraseñas con `password_hash()` / `password_verify()`.
- Sesiones de CodeIgniter.
- CSRF habilitado.
- Validaciones en backend.
- Permisos comprobados en controladores.
- Consultas concentradas en Models.

La `encryption_key` incluida corresponde al entorno local/académico y no debe reutilizarse como secreto en una instalación pública real.
