# Barber House - Instrucciones permanentes para agentes

## 1. Identidad del proyecto

- **Nombre académico:** Gestión de Turnos y Contabilidad.
- **Nombre comercial / interfaz:** Barber House.
- **Materia:** Práctica Profesionalizante 3.
- **Curso:** 2° 1ª.
- **Año:** 2026.
- **Equipo:** Carlos Balmaceda, Jesús Funes y Nahuel Vera.
- **Etapa actual:** Implementación y estabilización previa a Testing.

Este repositorio corresponde a un sistema web local para una peluquería. El objetivo es gestionar clientes, peluqueros, servicios, turnos, cobros, egresos y consultas contables básicas.

## 2. Leer antes de decidir

Para cualquier cambio funcional o técnico relevante, consultar estas fuentes en este orden:

1. `AGENTS.md` - reglas permanentes del repositorio.
2. `docs/DECISIONES_TECNICAS.md` - decisiones vigentes que pueden reemplazar documentos antiguos.
3. `docs/REQUERIMIENTOS_V1.md` - los 71 requerimientos formales de Análisis.
4. `docs/CONTEXTO_PROYECTO.md` - contexto del relevamiento, alcance y reglas de negocio.
5. `docs/Relevamiento.pdf` - fuente original de necesidades.
6. `docs/DER_Barber_House.png` y `docs/ESQUEMA_BD.md` - diseño de datos.
7. Código y pruebas actuales - muestran el estado implementado, pero **no redefinen los requerimientos**.

Si dos fuentes se contradicen, **no adivinar**. Aplicar la decisión vigente documentada en `docs/DECISIONES_TECNICAS.md`. Si el conflicto no está resuelto allí, informar el conflicto antes de cambiar comportamiento.

## 3. Stack definitivo

Mantener:

- PHP.
- CodeIgniter **3.1.13**.
- MySQL / MariaDB mediante XAMPP.
- Apache.
- Arquitectura MVC clásica.
- HTML, CSS y JavaScript existentes.
- Aplicación local utilizable desde navegador.
- Funcionamiento principal sin Internet.

### Decisión importante

El requerimiento formal 69 del documento de Análisis dice **CodeIgniter 4** porque es un documento anterior. Esa decisión fue reemplazada. La implementación vigente y definitiva utiliza **CodeIgniter 3.1.13**.

**NO migrar a CodeIgniter 4.**
**NO migrar a SQLite.**
**NO cambiar MySQL/MariaDB salvo decisión explícita del equipo.**

## 4. MVC debe ser fácil de identificar

### Controllers

Responsabilidades:

- recibir la petición;
- comprobar sesión y rol;
- recibir y validar datos;
- coordinar modelos;
- manejar transacciones cuando corresponda al caso de uso;
- preparar datos para la vista;
- redireccionar y mostrar mensajes.

Reglas:

- No escribir SQL en controladores.
- Evitar métodos gigantes.
- Evitar varias instrucciones en una misma línea.
- Mantener nombres descriptivos y flujo secuencial fácil de explicar.

### Models

Responsabilidades:

- consultas a MySQL;
- inserciones y actualizaciones;
- conteos;
- validaciones relacionadas con persistencia;
- bloqueos y consultas dentro de transacciones;
- devolver datos claros al controlador.

Reglas:

- SQL/Query Builder pertenece aquí, no en controladores ni vistas.
- Preferir Query Builder de CodeIgniter cuando sea suficiente.
- Las consultas SQL especiales deben estar justificadas y comentadas brevemente.

### Views

Responsabilidades:

- mostrar datos;
- formularios;
- tablas;
- estados;
- navegación.

Reglas:

- No ejecutar SQL.
- No mover reglas de negocio a JavaScript.
- Usar `if`, `else`, `for`, `foreach` y estructuras PHP simples cuando correspondan.
- Escapar salida de usuario (`html_escape` u opción equivalente existente).
- Mantener estética y clases CSS existentes salvo error concreto.

## 5. Estilo de código académico

El código debe ser correcto pero también **fácil de estudiar y defender**.

Preferir:

- variables descriptivas;
- una instrucción por línea;
- bloques cortos;
- `if` / `else`;
- `for`;
- `foreach`;
- `while` solo cuando sea natural;
- arrays;
- métodos pequeños;
- comentarios breves antes de reglas de negocio o bloques no obvios.

Ejemplo correcto:

```php
// Requerimiento 33: evitar superposición de turnos.
// Comprobar si el peluquero ya tiene un turno activo en ese intervalo.
$has_conflict = $this->Turno_model->hay_superposicion(
    $barber_id,
    $start_at,
    $end_at,
    $appointment_id
);

if ($has_conflict) {
    $errors[] = 'El peluquero ya tiene un turno en ese horario.';
}
```

Evitar comentarios que solo repitan la línea siguiente.

## 6. Trazabilidad de requerimientos

Cuando una regla de negocio sea importante o no obvia, se puede indicar el número formal:

```php
// REQ-33: Evitar superposición de turnos.
```

No es obligatorio etiquetar cada línea ni cada CRUD. Usar etiquetas principalmente en:

- reglas de agenda;
- históricos/snapshots;
- permisos;
- cobros e ingresos;
- cálculos contables;
- validaciones que protegen datos.

Para conocer la definición exacta de cada número, leer `docs/REQUERIMIENTOS_V1.md`.

## 7. Roles vigentes

### Recepcionista

Puede trabajar con:

- Inicio.
- Clientes.
- Agenda / Turnos.
- Cobros necesarios para la atención diaria.

No debe administrar:

- usuarios;
- precios;
- porcentajes;
- egresos;
- configuración financiera;
- correcciones económicas administrativas.

### Encargado

Puede realizar todo lo anterior y además:

- administrar peluqueros;
- administrar servicios y precios;
- administrar porcentajes;
- registrar/corregir egresos;
- consultar contabilidad;
- consultar liquidaciones;
- administrar usuarios.

**Los permisos se verifican siempre en backend. Ocultar un botón no es seguridad.**

## 8. Reglas de negocio críticas

Estas reglas no deben romperse:

1. Un turno cobrado no puede terminar cancelado, ausente o eliminado de forma incompatible.
2. Los cambios concurrentes de cobro/estado deben conservar el invariante anterior mediante transacción/bloqueo.
3. Un turno no puede superponerse con otro turno activo del mismo peluquero.
4. La superposición se calcula por intervalo de inicio/fin, no solo por igualdad de hora.
5. Turnos cancelados no bloquean disponibilidad.
6. El turno debe respetar días y horarios de trabajo del peluquero.
7. Cambiar horarios/días/servicios de un peluquero no puede dejar reservas futuras activas inválidas; el cambio debe rechazarse de forma atómica si existe conflicto.
8. Un turno puede incluir varios servicios.
9. El precio y duración aplicados al turno deben conservarse históricamente mediante snapshots.
10. Cambiar el precio actual de un servicio no modifica turnos históricos.
11. El importe real cobrado puede ser distinto al total de lista.
12. El ingreso económico surge del cobro; no duplicar la carga del mismo ingreso.
13. La comisión/porcentaje aplicado al peluquero debe conservarse históricamente al cobrar.
14. Las correcciones económicas sensibles pertenecen al Encargado.
15. Un usuario inactivo no puede iniciar sesión.
16. El sistema debe seguir siendo utilizable localmente sin Internet.

## 9. Base de datos

Motor definitivo: **MySQL/MariaDB de XAMPP**.

Estructura base esperada:

- `roles`
- `users`
- `clients`
- `barbers`
- `barber_schedules`
- `services`
- `barber_services`
- `appointments`
- `appointment_services`
- `payment_methods`
- `payments`
- `expense_categories`
- `expenses`

No agregar tablas o columnas por comodidad si el modelo actual ya permite resolver el requisito.

### Regla de seguridad

`database/barber_house.sql` representa una instalación nueva completa. No contiene
`DROP DATABASE` ni actualiza una base existente; falla si el nombre ya existe.

**Nunca ejecutar automáticamente el SQL de instalación sobre la base real `barber_house` durante pruebas o mantenimiento.**

Las pruebas deben usar una base temporal, por ejemplo `barber_house_test`.

Si una modificación estructural es realmente necesaria:

1. explicar por qué;
2. crear un script incremental en `database/updates/` o mecanismo equivalente vigente;
3. actualizar también el SQL de instalación limpia;
4. conservar datos históricos;
5. no ejecutar el cambio sobre la base real sin autorización explícita.

## 10. Alcance excluido

No agregar sin pedido explícito:

- reservas públicas por Internet;
- pagos online;
- stock;
- venta de productos;
- proveedores;
- e-commerce;
- WhatsApp API;
- notificaciones externas;
- microservicios;
- React;
- Node;
- Docker;
- ORM externo;
- arquitectura hexagonal;
- capas empresariales innecesarias.

## 11. Interfaz

Conservar la línea visual actual de Barber House:

- sidebar;
- modo claro/oscuro;
- responsive básico;
- formularios y tablas claros;
- badges de estado;
- navegación por rol.

No rediseñar si la tarea es de backend o legibilidad.

Todo botón visible debe tener una acción real o estar claramente deshabilitado.

## 12. Paginación

Convención actual: **25 registros por página** donde corresponde.

Aplicar principalmente a:

- clientes;
- historial de turnos;
- cobros;
- egresos.

No paginar la agenda diaria si eso empeora su uso.

Los filtros deben conservarse al cambiar de página.

## 13. Testing y estabilidad

Antes de dar por terminado un cambio relevante:

- ejecutar `php -l` en PHP modificado;
- ejecutar pruebas de helper;
- ejecutar la suite de integración cuando la tarea afecte reglas de negocio;
- comprobar ambos roles si cambia autorización;
- comprobar que no apareció SQL en controladores;
- verificar que las pruebas no dejen servidores `php -S`, bases temporales, sesiones o logs huérfanos.

Las pruebas de integración deben:

- trabajar con una base temporal;
- no tocar `barber_house`;
- tener timeouts;
- cerrar procesos y conexiones;
- limpiar recursos incluso ante error.

## 14. Estado técnico base conocido (2026-10-04)

Último estado informado por el equipo:

- 30 verificaciones de helper aprobadas.
- 172 verificaciones de integración aprobadas.
- Total: 202 verificaciones, 0 fallos.
- Auditoría funcional: 71/71 requerimientos completos.
- `php -l` limpio.
- Sin SQL en controladores.
- Paginación de 25 elementos implementada.
- Corrección de concurrencia cobro vs. estado implementada.
- Cambios de disponibilidad de peluqueros protegidos frente a turnos futuros.
- Sin cambios estructurales recientes en `database/`.
- Sin procesos PHP de prueba huérfanos al finalizar.

Este bloque es un **baseline**, no una excusa para omitir pruebas. Verificar el estado real del workspace antes de asumirlo.

## 15. Git

- No hacer `git push` salvo pedido explícito.
- No hacer commits salvo pedido explícito.
- No descartar cambios del usuario.
- Antes de refactors amplios, revisar `git diff`.
- Cambios funcionales pequeños y focalizados.

## 16. Criterio de decisión

Ante una duda:

1. proteger los requerimientos;
2. proteger datos históricos;
3. preferir la solución más simple que sea correcta;
4. mantener MVC visible;
5. evitar cambios de BD;
6. mantener el código fácil de explicar;
7. probar antes de declarar terminado.

No agregar complejidad solo porque sea técnicamente posible.
