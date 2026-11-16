# Matriz de trazabilidad - Barber House

Objetivo: ayudar a agentes y desarrolladores a ubicar rápidamente dónde debe vivir cada requerimiento.

Esta matriz es una guía de navegación, no reemplaza la auditoría del código real.

| Requerimientos | Área | Componentes principales esperados |
|---|---|---|
| REQ-01 a REQ-05 | Agenda | `Turnos.php`, `Turno_model.php`, vistas de turnos |
| REQ-06 a REQ-11 | Clientes | `Clientes.php`, `Cliente_model.php`, vistas de clientes |
| REQ-12 a REQ-21 | Peluqueros | `Peluqueros.php`, `Peluquero_model.php`, vistas de peluqueros |
| REQ-22 a REQ-29 | Servicios | `Servicios.php`, `Servicio_model.php`, relación appointment_services / barber_services |
| REQ-30 a REQ-42 | Turnos | `Turnos.php`, `Turno_model.php`, vistas de turnos |
| REQ-43 a REQ-48 | Cobros / ingresos | `Cobros.php`, `Cobro_model.php`, vistas de cobros |
| REQ-49 a REQ-60 | Contabilidad | `Contabilidad.php`, `Cobro_model.php`, `Egreso_model.php`, vistas de contabilidad |
| REQ-61 a REQ-63 | Roles/permisos | `Autenticacion.php`, `Usuarios.php`, `MY_Controller.php`, `Usuario_model.php`, navegación |
| REQ-64 a REQ-71 | No funcionales | configuración CI3, XAMPP, MySQL, MVC, interfaz |

## Cómo mantenerla

Cuando se implemente o cambie una regla relevante:

1. leer la definición exacta en `docs/REQUERIMIENTOS_V1.md`;
2. identificar Controller + Model + View + test afectado;
3. mantener la regla en la capa correcta;
4. agregar/actualizar prueba cuando sea una regla de negocio;
5. si el requerimiento queda implementado de forma distinta, actualizar esta matriz con archivos concretos.

## Convención de comentarios

Para reglas críticas se permite:

```php
// REQ-33: Evitar superposición de turnos.
```

No es necesario etiquetar CRUD obvios ni llenar el código de comentarios.

La guía archivo por archivo y los recorridos de los casos de uso están en
`docs/ESTRUCTURA_DEL_PROYECTO.md`. Las pruebas actuales se describen en
`tools/pruebas/README.md`.
