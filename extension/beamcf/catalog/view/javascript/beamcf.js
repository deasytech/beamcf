/* BeamCF theme: back-to-top control. Bootstrap handles the menu, drawer and dropdowns. */
(function () {
  var button = document.getElementById('bcf-top');

  if (!button) {
    return;
  }

  var toggle = function () {
    button.hidden = window.scrollY < 400;
  };

  button.addEventListener('click', function () {
    window.scrollTo({top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});

    var main = document.getElementById('bcf-main');

    if (main) {
      main.focus({preventScroll: true});
    }
  });

  window.addEventListener('scroll', toggle, {passive: true});

  toggle();
})();

/* Catalog: sort/limit navigation, filter form behaviour and grid/list toggle. Everything here is progressive:
   the filter form is a plain GET form and works without this script. */
(function () {
  document.querySelectorAll('select[data-bcf-nav]').forEach(function (select) {
    select.addEventListener('change', function () {
      window.location.href = select.value;
    });
  });

  document.querySelectorAll('form[data-bcf-filter]').forEach(function (form) {
    // Keep the URL clean: do not send empty price or search fields.
    form.addEventListener('submit', function () {
      form.querySelectorAll('input[type="number"], input[type="search"]').forEach(function (input) {
        if (input.value === '') {
          input.disabled = true;
        }
      });
    });

    // Checkboxes and radios apply immediately; the Apply button remains for price and keyboard users.
    form.addEventListener('change', function (event) {
      if (event.target.matches('input[type="checkbox"], input[type="radio"]')) {
        form.requestSubmit();
      }
    });
  });

  // Dual price slider kept in sync with the number inputs.
  document.querySelectorAll('[data-bcf-range]').forEach(function (range) {
    var form = range.closest('form');
    var lo = range.querySelector('[data-bcf-range-min]');
    var hi = range.querySelector('[data-bcf-range-max]');
    var nmin = form.querySelector('input[name="price_min"]');
    var nmax = form.querySelector('input[name="price_max"]');
    var floor = parseFloat(range.getAttribute('data-min'));
    var ceil = parseFloat(range.getAttribute('data-max'));

    lo.addEventListener('input', function () {
      if (+lo.value > +hi.value) { lo.value = hi.value; }
      nmin.value = +lo.value === floor ? '' : lo.value;
    });

    hi.addEventListener('input', function () {
      if (+hi.value < +lo.value) { hi.value = lo.value; }
      nmax.value = +hi.value === ceil ? '' : hi.value;
    });

    // Submit once the handle is released.
    [lo, hi].forEach(function (input) {
      input.addEventListener('change', function () { form.requestSubmit(); });
    });

    nmin.addEventListener('input', function () { lo.value = nmin.value === '' ? floor : nmin.value; });
    nmax.addEventListener('input', function () { hi.value = nmax.value === '' ? ceil : nmax.value; });
  });

  var grid = document.getElementById('bcf-product-grid');
  var buttons = document.querySelectorAll('[data-bcf-view]');

  if (!grid || !buttons.length) {
    return;
  }

  var setView = function (view) {
    grid.setAttribute('data-view', view);

    buttons.forEach(function (button) {
      var on = button.getAttribute('data-bcf-view') === view;

      button.classList.toggle('active', on);
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var view = button.getAttribute('data-bcf-view');

      setView(view);

      try {
        window.localStorage.setItem('bcf-view', view);
      } catch (e) {
        // Storage can be unavailable (private mode); the choice then lasts for this page only.
      }
    });
  });

  try {
    if (window.localStorage.getItem('bcf-view') === 'list') {
      setView('list');
    }
  } catch (e) {
    // Ignore: default grid view.
  }
})();
