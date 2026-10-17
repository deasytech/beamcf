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

/* Quick view: loads the server-rendered fragment from extension/beamcf/product/quickview into a native <dialog>
   (focus trap, Escape and inert background come from showModal). Adding to the cart posts to the core
   checkout/cart.add route; no prices or totals are calculated here. */
(function () {
  var dialog = document.getElementById('bcf-qv');
  var content = document.getElementById('bcf-qv-content');

  if (!dialog || !content || typeof dialog.showModal !== 'function') {
    // Without <dialog> support the card's quick view button does nothing; the product link still works.
    document.querySelectorAll('[data-bcf-quickview]').forEach(function (button) { button.hidden = true; });
    return;
  }

  var trigger = null;
  var request = 0;
  var pending = false;

  var text = function (name) { return dialog.getAttribute('data-' + name) || ''; };

  var message = function (kind, html) {
    var status = document.getElementById('bcf-qv-status');

    if (!status) { return; }

    status.innerHTML = '';

    if (html) {
      var note = document.createElement('div');
      note.className = 'alert alert-' + kind;
      note.innerHTML = html;
      status.appendChild(note);
      status.scrollIntoView({block: 'nearest'});
    }
  };

  var showState = function (html, busy) {
    content.setAttribute('aria-busy', busy ? 'true' : 'false');
    content.innerHTML = html;
  };

  var failure = function (url) {
    showState('<div class="bcf-qv-body bcf-qv-empty"><p role="alert"></p><button type="button" class="bcf-qv-retry"></button></div>', false);

    content.querySelector('p').textContent = text('failed');

    var retry = content.querySelector('.bcf-qv-retry');
    retry.textContent = text('retry');
    retry.addEventListener('click', function () { load(url); });
    retry.focus();
  };

  var ready = function () {
    var body = content.querySelector('[data-bcf-qv-ready]');

    if (!body) { return; }

    var name = body.querySelector('.bcf-qv-name');

    name.id = 'bcf-qv-title';
    name.tabIndex = -1;
    dialog.setAttribute('aria-labelledby', 'bcf-qv-title');
    dialog.removeAttribute('aria-label');
    name.focus({preventScroll: true});
  };

  var load = function (url) {
    var id = ++request;

    showState('<div class="bcf-qv-body bcf-qv-empty"><p role="status"></p></div>', true);
    content.querySelector('p').textContent = text('loading');

    fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(function (response) {
        return response.text().then(function (html) { return {ok: response.ok, html: html}; });
      })
      .then(function (result) {
        if (id !== request) { return; }

        // A 404 carries a readable "not available" fragment; any other failure is a retryable error.
        if (!result.ok && result.html.indexOf('data-bcf-qv-error') === -1) {
          failure(url);
          return;
        }

        showState(result.html, false);
        ready();
      })
      .catch(function () {
        if (id === request) { failure(url); }
      });
  };

  var open = function (button) {
    trigger = button;
    pending = false;
    dialog.removeAttribute('aria-labelledby');
    dialog.setAttribute('aria-label', button.getAttribute('title') || '');
    document.documentElement.classList.add('bcf-qv-open');

    if (!dialog.open) { dialog.showModal(); }

    load(button.getAttribute('data-bcf-quickview'));
  };

  dialog.addEventListener('close', function () {
    request++;
    document.documentElement.classList.remove('bcf-qv-open');
    content.innerHTML = '';

    if (trigger && document.contains(trigger)) { trigger.focus(); }

    trigger = null;
  });

  var cartRefresh = function () {
    var url = text('cart').replace(/&amp;/g, '&');

    if (window.jQuery) {
      window.jQuery('#cart').load(url);
    }
  };

  var setImage = function (index) {
    var thumbs = content.querySelectorAll('[data-bcf-qv-image]');
    var main = document.getElementById('bcf-qv-main');

    if (!thumbs.length || !main) { return; }

    index = (index + thumbs.length) % thumbs.length;

    thumbs.forEach(function (thumb, i) {
      if (i === index) {
        thumb.setAttribute('aria-current', 'true');
        main.src = thumb.getAttribute('data-bcf-qv-image');
      } else {
        thumb.removeAttribute('aria-current');
      }
    });
  };

  var clearErrors = function (form) {
    form.querySelectorAll('.is-invalid').forEach(function (node) {
      node.classList.remove('is-invalid');
      node.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(function (node) {
      node.classList.remove('d-block');
      node.textContent = '';
    });
    message('', '');
  };

  var showErrors = function (form, errors) {
    var first = null;

    Object.keys(errors).forEach(function (key) {
      if (key === 'warning') {
        message('danger', errors[key]);
        return;
      }

      var slug = key.replace(/_/g, '-');
      var note = document.getElementById('bcf-qv-error-' + slug);
      var field = document.getElementById('bcf-qv-' + slug);

      if (key === 'subscription') { field = document.getElementById('bcf-qv-subscription'); }

      if (note) {
        note.innerHTML = errors[key];
        note.classList.add('d-block');
      }

      if (field) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        first = first || field;
      }
    });

    if (first) {
      var focusable = first.matches('input, select, textarea, button') ? first : first.querySelector('input, select, textarea, button');

      (focusable || first).focus();
    }
  };

  var submit = function (form) {
    if (pending) { return; }

    var button = document.getElementById('bcf-qv-add');

    // A response that arrives after the dialog was closed or reopened belongs to a dialog that no longer exists.
    var id = request;
    var current = function () { return id === request; };

    pending = true;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    clearErrors(form);

    fetch(form.action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
      body: new URLSearchParams(new FormData(form)).toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        // The cart was changed on the server either way, so the header is always refreshed.
        if (json.success) { cartRefresh(); }

        if (!current()) { return; }

        if (typeof json.error === 'string') {
          message('danger', json.error);
        } else if (json.error) {
          showErrors(form, json.error);
        }

        if (json.success) {
          message('success', json.success);
        }
      })
      .catch(function () { if (current()) { message('danger', text('failed')); } })
      .then(function () {
        if (!current()) { return; }

        pending = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
      });
  };

  var upload = function (button) {
    var input = document.createElement('input');

    input.type = 'file';
    input.addEventListener('change', function () {
      var file = input.files[0];

      if (!file) { return; }

      if (file.size > parseInt(button.getAttribute('data-size-max'), 10)) {
        message('danger', button.getAttribute('data-size-error'));
        return;
      }

      var body = new FormData();
      body.append('file', file);
      button.disabled = true;

      fetch(button.getAttribute('data-bcf-upload').replace(/&amp;/g, '&'), {method: 'POST', credentials: 'same-origin', body: body})
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (json.error) {
            message('danger', json.error);
          }

          if (json.code) {
            document.getElementById(button.getAttribute('data-target')).value = json.code;
            button.nextElementSibling.textContent = file.name;
            message('success', json.success || '');
          }
        })
        .catch(function () { message('danger', text('failed')); })
        .then(function () { button.disabled = false; });
    });
    input.click();
  };

  var wishlist = function (form) {
    var body = new URLSearchParams({product_id: form.elements.product_id.value}).toString();

    fetch(form.getAttribute('data-wishlist').replace(/&amp;/g, '&'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
      body: body
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (json.success) {
          message('success', json.success);
        } else if (json.error) {
          message('danger', typeof json.error === 'string' ? json.error : (json.error.warning || text('wishlist-failed')));
        }
      })
      .catch(function () { message('danger', text('wishlist-failed')); });
  };

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-bcf-quickview]');

    if (button) {
      event.preventDefault();
      open(button);
    }
  });

  dialog.addEventListener('click', function (event) {
    var target = event.target;

    if (target === dialog || target.closest('[data-bcf-qv-close]')) {
      dialog.close();
      return;
    }

    var thumb = target.closest('[data-bcf-qv-image]');

    if (thumb) {
      setImage(Array.prototype.indexOf.call(content.querySelectorAll('[data-bcf-qv-image]'), thumb));
      return;
    }

    var step = target.closest('[data-bcf-qv-step]');

    if (step) {
      var current = content.querySelector('[data-bcf-qv-image][aria-current]');
      var all = Array.prototype.slice.call(content.querySelectorAll('[data-bcf-qv-image]'));

      setImage(all.indexOf(current) + parseInt(step.getAttribute('data-bcf-qv-step'), 10));
      return;
    }

    var qty = target.closest('[data-bcf-qv-qty]');

    if (qty) {
      var input = document.getElementById('bcf-qv-quantity');
      var min = parseInt(input.getAttribute('data-min'), 10) || 1;

      input.value = Math.max(min, (parseInt(input.value, 10) || min) + parseInt(qty.getAttribute('data-bcf-qv-qty'), 10));
      return;
    }

    var file = target.closest('[data-bcf-upload]');

    if (file) {
      upload(file);
      return;
    }

    if (target.closest('#bcf-qv-wish')) {
      wishlist(document.getElementById('bcf-qv-form'));
    }
  });

  dialog.addEventListener('change', function (event) {
    if (event.target.id === 'bcf-qv-subscription') {
      var option = event.target.options[event.target.selectedIndex];

      document.getElementById('bcf-qv-plan-text').textContent = option ? (option.getAttribute('data-description') || '') : '';
    }
  });

  dialog.addEventListener('submit', function (event) {
    if (event.target.id === 'bcf-qv-form') {
      event.preventDefault();
      submit(event.target);
    }
  });
})();

/* Cart drawer: the header cart fragment (#cart) is reloaded after every cart change, so the drawer copies the
   server-rendered panel from it on open and again whenever #cart is replaced while the drawer is open. */
(function () {
  var drawer = document.getElementById('bcf-cart-drawer');
  var cart = document.getElementById('cart');
  var body = document.getElementById('bcf-cart-body');

  if (!drawer || !cart || !body) { return; }

  var fill = function () {
    var panel = cart.querySelector('template.bcf-cart-panel');

    body.innerHTML = panel ? panel.innerHTML : '';
  };

  drawer.addEventListener('show.bs.offcanvas', fill);

  new MutationObserver(function () {
    if (drawer.classList.contains('show')) { fill(); }
  }).observe(cart, {childList: true});
})();

/* Account sidebar: mark the link for the current page. */
(function () {
  var links = document.querySelectorAll('.bcf-acc-nav a');
  var route = new URLSearchParams(window.location.search).get('route');
  if (!route) { return; }
  route = route.split('|')[0];
  var best = null;
  links.forEach(function (a) {
    try {
      var r = (new URL(a.href, window.location.href).searchParams.get('route') || '').split('|')[0];
      if (r === route) { best = a; }
    } catch (e) { /* ignore malformed links */ }
  });
  if (best) { best.setAttribute('aria-current', 'page'); }
})();
