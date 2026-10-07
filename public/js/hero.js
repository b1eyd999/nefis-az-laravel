/**
 * The banner's slides: dots, a swipe, and turning on their own.
 *
 * Loaded by partials/hero.blade.php, so it runs on whichever page the
 * owner has put a banner on, not only the home page.
 */
/* The opening banner's slides: dots, arrows, a swipe, and turning on their own. */
(function(){
  var hero = document.querySelector('.hero-slider');
  if (!hero) return;
  var slides = Array.prototype.slice.call(hero.querySelectorAll('.hero-slide'));
  var dots = Array.prototype.slice.call(hero.querySelectorAll('.hero-dot'));
  var current = 0, timer = null;
  var autoplay = hero.dataset.autoplay === '1' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var every = (parseInt(hero.dataset.interval, 10) || 6) * 1000;

  function go(i){
    i = (i + slides.length) % slides.length;
    if (i === current) return;
    slides[current].classList.remove('is-on');
    slides[current].setAttribute('aria-hidden', 'true');
    slides[current].querySelectorAll('a').forEach(function(a){ a.setAttribute('tabindex', '-1'); });
    slides[i].classList.add('is-on');
    slides[i].removeAttribute('aria-hidden');
    slides[i].querySelectorAll('a').forEach(function(a){ a.removeAttribute('tabindex'); });
    dots.forEach(function(d, k){ d.classList.toggle('is-on', k === i); if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
    current = i;
  }
  function start(){ stop(); if (autoplay) timer = setInterval(function(){ go(current + 1); }, every); }
  function stop(){ if (timer) clearInterval(timer); timer = null; }

  hero.querySelectorAll('.hero-arrow').forEach(function(b){
    b.addEventListener('click', function(){ go(current + parseInt(b.dataset.dir, 10)); start(); });
  });
  dots.forEach(function(d){ d.addEventListener('click', function(){ go(parseInt(d.dataset.go, 10)); start(); }); });

  /* a swipe on a phone */
  var x0 = null;
  hero.addEventListener('touchstart', function(e){ x0 = e.touches[0].clientX; }, { passive: true });
  hero.addEventListener('touchend', function(e){
    if (x0 === null) return;
    var dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) { go(current + (dx < 0 ? 1 : -1)); start(); }
    x0 = null;
  });

  /* hold still while being read or when the tab is away */
  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);
  hero.addEventListener('focusin', stop);
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
  start();
})();
