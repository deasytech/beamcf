// BeamCF homepage: Deals of the Day countdown. The end time comes from the product special's real end date.
(function () {
  document.querySelectorAll('.bcf-countdown').forEach(function (el) {
    var end = parseInt(el.dataset.end, 10) * 1000;
    // Use the server clock as the reference so a wrong device clock does not change the remaining time.
    var offset = parseInt(el.dataset.now, 10) * 1000 - Date.now();

    var tick = function () {
      var left = Math.max(0, Math.floor((end - (Date.now() + offset)) / 1000));

      if (left === 0) {
        el.hidden = true;
        clearInterval(timer);
      }

      var parts = {d: Math.floor(left / 86400), h: Math.floor(left % 86400 / 3600), m: Math.floor(left % 3600 / 60), s: left % 60};

      Object.keys(parts).forEach(function (unit) {
        var node = el.querySelector('[data-unit="' + unit + '"]');

        if (node) {
          node.textContent = unit === 'd' ? parts[unit] : String(parts[unit]).padStart(2, '0');
        }
      });
    };

    var timer = setInterval(tick, 1000);

    tick();
  });
})();

// BeamCF homepage: product carousels (CSS scroll-snap track with previous/next buttons).
(function () {
  document.querySelectorAll('.bcf-carousel').forEach(function (root) {
    var track = root.querySelector('.bcf-track');
    var prev = root.querySelector('.bcf-carousel-prev');
    var next = root.querySelector('.bcf-carousel-next');

    var update = function () {
      var overflow = track.scrollWidth - track.clientWidth > 4;

      prev.hidden = !overflow || track.scrollLeft <= 4;
      next.hidden = !overflow || track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
    };

    var scroll = function (direction) {
      track.scrollBy({left: direction * track.clientWidth * 0.9, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
    };

    prev.addEventListener('click', function () { scroll(-1); });
    next.addEventListener('click', function () { scroll(1); });
    track.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);

    // Tab panes are hidden until shown, so measure again when the tab opens.
    document.addEventListener('shown.bs.tab', update);

    update();
  });
})();
