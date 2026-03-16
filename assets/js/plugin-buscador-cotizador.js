(function () {
  function formatCurrency(amount) {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount);
  }

  function safeJsonParse(rawValue) {
    try {
      return JSON.parse(rawValue || '{}');
    } catch (error) {
      return {};
    }
  }

  function bindEmailInquiryModal() {
    var modal = document.querySelector('[data-pbc-email-modal]');
    if (!modal) {
      return;
    }

    var form = modal.querySelector('[data-pbc-email-form]');
    var feedback = modal.querySelector('[data-pbc-email-feedback]');
    var selected = modal.querySelector('[data-pbc-email-selected]');

    function closeModal() {
      modal.setAttribute('hidden', 'hidden');
      document.body.classList.remove('pbc-modal-open');
    }

    function openModal(inquiry) {
      var context = inquiry || {};

      form.querySelector('[name="item_name"]').value = context.item_name || '';
      form.querySelector('[name="destination"]').value = context.destination || '';
      form.querySelector('[name="travel_date"]').value = context.travel_date || '';
      form.querySelector('[name="nights"]').value = context.nights || '';
      form.querySelector('[name="passengers"]').value = context.passengers || '';

      if (selected) {
        selected.textContent =
          'Opción seleccionada: ' +
          (context.item_name || 'Consulta general') +
          ' · Destino: ' +
          (context.destination || 'No especificado') +
          ' · Fecha: ' +
          (context.travel_date || 'No especificada') +
          ' · Noches: ' +
          (context.nights || 'No especificado') +
          ' · Pasajeros: ' +
          (context.passengers || 'No especificado');
      }

      if (feedback) {
        feedback.textContent = '';
        feedback.classList.remove('is-error', 'is-success');
      }

      modal.removeAttribute('hidden');
      document.body.classList.add('pbc-modal-open');
    }

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('.pbc-email-trigger');
      if (trigger) {
        event.preventDefault();
        openModal(safeJsonParse(trigger.getAttribute('data-pbc-inquiry')));
        return;
      }

      var closeTrigger = event.target.closest('[data-pbc-email-close]');
      if (closeTrigger) {
        event.preventDefault();
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
        closeModal();
      }
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (!window.pbcFrontend || !window.pbcFrontend.ajaxUrl || !window.pbcFrontend.nonce) {
        if (feedback) {
          feedback.textContent = 'No se pudo enviar la consulta. Recargá la página e intentá nuevamente.';
          feedback.classList.add('is-error');
        }
        return;
      }

      var formData = new FormData(form);
      formData.append('action', 'pbc_send_email_inquiry');
      formData.append('nonce', window.pbcFrontend.nonce);

      fetch(window.pbcFrontend.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!feedback) {
            return;
          }

          feedback.classList.remove('is-error', 'is-success');

          if (data && data.success) {
            feedback.textContent = data.data && data.data.message ? data.data.message : 'Consulta enviada correctamente.';
            feedback.classList.add('is-success');
            form.reset();
            setTimeout(closeModal, 1200);
            return;
          }

          feedback.textContent = data && data.data && data.data.message ? data.data.message : 'No se pudo enviar la consulta.';
          feedback.classList.add('is-error');
        })
        .catch(function () {
          if (feedback) {
            feedback.textContent = 'Hubo un problema de conexión al enviar la consulta.';
            feedback.classList.remove('is-success');
            feedback.classList.add('is-error');
          }
        });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-pbc-form]');
    if (!form) {
      return;
    }

    event.preventDefault();

    var root = form.closest('[data-pbc-root]');
    var result = root ? root.querySelector('[data-pbc-result]') : null;

    var dias = Number(form.querySelector('[name="dias"]').value || 0);
    var personas = Number(form.querySelector('[name="personas"]').value || 0);
    var total = dias * personas * 18000;

    if (result) {
      result.textContent = 'Cotización estimada: ' + formatCurrency(total);
    }
  });

  bindEmailInquiryModal();
})();
