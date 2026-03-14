(function () {
  function formatCurrency(amount) {
    return new Intl.NumberFormat('es-AR', {
      style: 'currency',
      currency: 'ARS',
      maximumFractionDigits: 0,
    }).format(amount);
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
})();
