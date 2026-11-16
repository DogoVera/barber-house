/**
 * Interacción local: tema, menú, estimados visuales, consulta AJAX y confirmaciones.
 * Los atributos data-* de las vistas conectan cada bloque sin depender de sus rutas físicas.
 * El servidor conserva la autoridad sobre disponibilidad, precios, permisos y cobros.
 */
(function () {
  'use strict';

  const page = document.documentElement;
  const body = document.body;

  // ------------------------------------------------------------
  // Tema claro / oscuro
  // ------------------------------------------------------------
  /** Recibe light/dark, actualiza el documento y persiste la preferencia local. */
  function cambiarTema(theme) {
    page.setAttribute('data-theme', theme);
    localStorage.setItem('bh-theme', theme);

    document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
      icon.textContent = theme === 'dark' ? '☀' : '☾';
    });
  }

  cambiarTema(localStorage.getItem('bh-theme') || 'dark');

  document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      const nextTheme = page.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      cambiarTema(nextTheme);
    });
  });

  // ------------------------------------------------------------
  // Sidebar
  // ------------------------------------------------------------
  if (localStorage.getItem('bh-side') === 'small') {
    body.classList.add('collapsed');
  }

  document.querySelectorAll('[data-side]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (window.innerWidth <= 760) {
        body.classList.toggle('mobile');
        return;
      }

      body.classList.toggle('collapsed');
      localStorage.setItem('bh-side', body.classList.contains('collapsed') ? 'small' : 'full');
    });
  });

  // ------------------------------------------------------------
  // Servicios: total y duración estimados en pantalla
  // El backend vuelve a calcular todo antes de guardar.
  // ------------------------------------------------------------
  /** Suma los checkboxes seleccionados y actualiza etiquetas; no guarda importes. */
  function calcularServicios() {
    let total = 0;
    let minutes = 0;

    document.querySelectorAll('[data-service-check]:checked').forEach(function (checkbox) {
      total += Number(checkbox.dataset.price || 0);
      minutes += Number(checkbox.dataset.minutes || 0);
    });

    const totalLabel = document.querySelector('[data-service-total]');
    const minutesLabel = document.querySelector('[data-service-minutes]');

    if (totalLabel) {
      totalLabel.textContent = '$' + total.toLocaleString('es-AR');
    }
    if (minutesLabel) {
      minutesLabel.textContent = minutes + ' min';
    }
  }

  document.querySelectorAll('[data-service-check]').forEach(function (checkbox) {
    checkbox.addEventListener('change', calcularServicios);
  });
  calcularServicios();

  // ------------------------------------------------------------
  // Disponibilidad de turnos (REQ-05 / REQ-19)
  // Solo ayuda al usuario a elegir. El servidor vuelve a validar al guardar.
  // ------------------------------------------------------------
  const availabilityBox = document.querySelector('[data-availability-box]');

  /** Consulta el JSON del backend y ofrece botones de hora; comunica errores sin guardar un turno. */
  async function cargarDisponibilidad() {
    if (!availabilityBox) {
      return;
    }

    const barber = document.querySelector('[data-availability-barber]');
    const date = document.querySelector('[data-availability-date]');
    const message = document.querySelector('[data-availability-message]');
    const slotsBox = document.querySelector('[data-availability-slots]');
    const timeInput = document.querySelector('[data-availability-time]');
    const appointmentId = document.querySelector('[data-appointment-id]');
    const serviceChecks = Array.from(document.querySelectorAll('[data-service-check]:checked'));

    slotsBox.innerHTML = '';

    if (!barber || !date || !barber.value || !date.value || serviceChecks.length === 0) {
      message.textContent = 'Elegí peluquero, fecha y al menos un servicio.';
      return;
    }

    const params = new URLSearchParams();
    params.append('barber_id', barber.value);
    params.append('date', date.value);
    serviceChecks.forEach(function (checkbox) {
      params.append('service_ids[]', checkbox.value);
    });
    if (appointmentId && appointmentId.value) {
      params.append('appointment_id', appointmentId.value);
    }

    message.textContent = 'Consultando horarios disponibles...';

    try {
      const response = await fetch(availabilityBox.dataset.url + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (!response.ok) {
        throw new Error('No se pudo consultar la disponibilidad.');
      }

      const data = await response.json();
      if (data.error) {
        message.textContent = data.error;
        return;
      }

      if (!data.slots || data.slots.length === 0) {
        message.textContent = 'No hay horarios libres para la combinación seleccionada.';
        return;
      }

      message.textContent = 'Duración estimada: ' + data.minutes + ' min. Elegí un horario:';

      data.slots.forEach(function (slot) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        button.textContent = slot;
        button.addEventListener('click', function () {
          timeInput.value = slot;
        });
        slotsBox.appendChild(button);
      });
    } catch (error) {
      message.textContent = 'No se pudo consultar la disponibilidad. Intentá nuevamente.';
    }
  }

  const availabilityButton = document.querySelector('[data-check-availability]');
  if (availabilityButton) {
    availabilityButton.addEventListener('click', cargarDisponibilidad);
  }

  document.querySelectorAll('[data-service-check], [data-availability-barber], [data-availability-date]').forEach(function (element) {
    element.addEventListener('change', function () {
      // Limpiamos horarios viejos cuando cambia una condición de disponibilidad.
      if (availabilityBox) {
        document.querySelector('[data-availability-slots]').innerHTML = '';
        document.querySelector('[data-availability-message]').textContent = 'Presioná Consultar para actualizar los horarios disponibles.';
      }
    });
  });

  // ------------------------------------------------------------
  // Confirmaciones de acciones sensibles
  // ------------------------------------------------------------
  document.querySelectorAll('[data-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!confirm(button.dataset.confirm || '¿Confirmar acción?')) {
        event.preventDefault();
      }
    });
  });
})();
