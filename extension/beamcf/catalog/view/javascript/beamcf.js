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
