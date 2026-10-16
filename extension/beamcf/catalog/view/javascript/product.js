/* BeamCF product page: gallery, lightbox, quantity stepper, tab links and add to cart. Adding to the cart posts to the
   core checkout/cart.add route; prices, options and validation all come from the server. */
(function () {
  var config = window.bcfProduct || {};
  var form = document.getElementById('form-product');
  var status = document.getElementById('bcf-pd-status');

  var message = function (kind, html) {
    if (!status) { return; }

    status.innerHTML = '';

    if (html) {
      var note = document.createElement('div');
      note.className = 'alert alert-' + kind;
      note.innerHTML = html;
      status.appendChild(note);
    }
  };

  /* Gallery */
  var main = document.getElementById('bcf-pd-main');
  var zoomLink = document.querySelector('[data-bcf-zoom]');
  var thumbs = document.querySelectorAll('[data-bcf-pd-image]');

  var setImage = function (index) {
    if (!thumbs.length || !main) { return; }

    index = (index + thumbs.length) % thumbs.length;

    thumbs.forEach(function (thumb, i) {
      if (i === index) {
        thumb.setAttribute('aria-current', 'true');
        main.src = thumb.getAttribute('data-bcf-pd-image');
        zoomLink.href = zoomLink.dataset.bcfZoom = thumb.getAttribute('data-popup');
      } else {
        thumb.removeAttribute('aria-current');
      }
    });
  };

  var current = function () {
    for (var i = 0; i < thumbs.length; i++) {
      if (thumbs[i].hasAttribute('aria-current')) { return i; }
    }

    return 0;
  };

  thumbs.forEach(function (thumb, i) {
    thumb.addEventListener('click', function () { setImage(i); });
  });

  document.querySelectorAll('[data-bcf-pd-step]').forEach(function (button) {
    button.addEventListener('click', function () { setImage(current() + parseInt(button.getAttribute('data-bcf-pd-step'), 10)); });
  });

  /* Lightbox: a native dialog, so Escape, focus trap and the inert background come for free. */
  var zoom = document.getElementById('bcf-zoom');

  if (zoomLink && zoom && typeof zoom.showModal === 'function') {
    zoomLink.addEventListener('click', function (event) {
      event.preventDefault();
      document.getElementById('bcf-zoom-img').src = zoomLink.dataset.bcfZoom;
      zoom.showModal();
    });

    zoom.addEventListener('click', function (event) {
      if (event.target === zoom) { zoom.close(); }
    });

    zoom.addEventListener('close', function () { zoomLink.focus(); });
  }

  /* Quantity stepper */
  var quantity = document.getElementById('input-quantity');

  document.querySelectorAll('[data-bcf-pd-qty]').forEach(function (button) {
    button.addEventListener('click', function () {
      var min = parseInt(quantity.getAttribute('data-min'), 10) || 1;
      var value = (parseInt(quantity.value, 10) || min) + parseInt(button.getAttribute('data-bcf-pd-qty'), 10);

      quantity.value = Math.max(min, value);
    });
  });

  /* Links to the review tab */
  document.querySelectorAll('[data-bcf-tab]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      var tab = document.getElementById(link.getAttribute('data-bcf-tab') + '-btn');

      if (!tab) { return; }

      event.preventDefault();
      tab.click();
      tab.scrollIntoView({block: 'start'});
      document.getElementById(link.getAttribute('data-bcf-tab')).focus({preventScroll: true});
    });
  });

  /* Add to cart */
  if (!form) { return; }

  var clearErrors = function () {
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

  var showErrors = function (errors) {
    var first = null;

    Object.keys(errors).forEach(function (key) {
      if (key === 'warning') {
        message('danger', errors[key]);
        return;
      }

      var slug = key.replace(/_/g, '-');
      var note = document.getElementById('error-' + slug);
      var field = document.getElementById('input-' + slug);

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

  var pending = false;
  var buyNow = false;

  // Buy now is the normal add to cart request; only after a successful add does it continue to the real checkout.
  var buy = document.getElementById('button-buy');

  if (buy) { buy.addEventListener('click', function () { buyNow = true; }); }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var goCheckout = buyNow;

    buyNow = false;

    if (pending) { return; }

    var button = document.getElementById('button-cart');
    var buyButton = document.getElementById('button-buy');

    pending = true;
    button.disabled = true;
    if (buyButton) { buyButton.disabled = true; }
    button.setAttribute('aria-busy', 'true');
    clearErrors();

    fetch(config.cart, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
      body: new URLSearchParams(new FormData(form)).toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (typeof json.error === 'string') {
          message('danger', json.error);
        } else if (json.error) {
          showErrors(json.error);
        }

        if (json.success) {
          message('success', json.success);

          if (window.jQuery) { window.jQuery('#cart').load(config.cartInfo); }

          if (goCheckout && config.checkout) { window.location.href = config.checkout; }
        }
      })
      .catch(function () { message('danger', config.failed || ''); })
      .then(function () {
        pending = false;
        button.disabled = false;
        if (buyButton) { buyButton.disabled = false; }
        button.removeAttribute('aria-busy');
      });
  });

  /* The core review form's star radios have no accessible name; label them from the form's own "Rating" label. */
  var ratingGroup = document.getElementById('input-rating');
  if (ratingGroup) {
    var ratingLabel = ratingGroup.parentNode.querySelector('label');
    var ratingName = ratingLabel ? ratingLabel.textContent.trim() : '';
    ratingGroup.setAttribute('role', 'radiogroup');
    if (ratingName) { ratingGroup.setAttribute('aria-label', ratingName); }
    ratingGroup.querySelectorAll('input[name="rating"]').forEach(function (radio) {
      radio.setAttribute('aria-label', (ratingName ? ratingName + ' ' : '') + radio.value + '/5');
    });
  }
})();
