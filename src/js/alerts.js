(function(){
  const wrapper = document.querySelector('.alerts-wrapper');
  if (!wrapper) return;

  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const alerts = Array.from(wrapper.querySelectorAll('.alert'));

  // marca que JS está listo para activar animaciones
  wrapper.classList.add('js-ready');

  alerts.forEach((alertEl, idx) => {
    // staggers con variable CSS
    alertEl.style.setProperty('--i', idx);

    // show con pequeño delay para que entre en cascada
    requestAnimationFrame(() => {
      // usamos setTimeout para escalonar un poco
      setTimeout(() => {
        alertEl.classList.add('show');
        alertEl.setAttribute('aria-hidden', 'false');
      }, idx * 70);
    });

    // leer duración desde data-duration o fallback
    const DEFAULT_DURATION = 5000;
    const duration = parseInt(alertEl.dataset.duration, 10) || DEFAULT_DURATION;
    let dismissTimer = null;

    // función para iniciar auto-dismiss
    const startTimer = () => {
      if (prefersReduced) return; // no auto-dismiss si usuario pidió reducir movimiento
      clearTimer();
      dismissTimer = setTimeout(() => dismiss(alertEl), duration);
    };
    const clearTimer = () => {
      if (dismissTimer) { clearTimeout(dismissTimer); dismissTimer = null; }
    };

    // pausa el timer al pasar el mouse o entrar con foco
    alertEl.addEventListener('mouseenter', clearTimer);
    alertEl.addEventListener('focusin', clearTimer);
    alertEl.addEventListener('mouseleave', startTimer);
    alertEl.addEventListener('focusout', startTimer);

    // close button
    const btn = alertEl.querySelector('.alert-close');
    if (btn) {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        dismiss(alertEl);
      });
    }

    // start auto-dismiss
    startTimer();
  });

  // dismiss: anima la altura y elimina del DOM en transitionend
  function dismiss(el) {
    if (!el || el.classList.contains('hiding')) return;
    // poner altura explícita antes de colapsar para animar height (max-height también compatible)
    const currentHeight = el.offsetHeight;
    el.style.height = currentHeight + 'px';
    // forzar reflow
    void el.offsetHeight;

    // añadir clase que aplica max-height:0 y padding:0
    el.classList.add('hiding');
    el.setAttribute('aria-hidden', 'true');

    // después de una raf, fijar height a 0 para animar colapso (maneja navegadores que no respetan max-height)
    requestAnimationFrame(() => {
      el.style.height = '0px';
    });

    // al terminar la transición eliminamos el elemento
    const onEnd = (ev) => {
      // filtrar por propiedades de interés (opacity o max-height)
      if (ev.propertyName && !/opacity|max-height|height/.test(ev.propertyName)) return;
      el.removeEventListener('transitionend', onEnd);
      if (el.parentNode) el.parentNode.removeChild(el);
    };
    el.addEventListener('transitionend', onEnd);

    // fallback: si no hay transitionend (por alguna razón), remove después de 500ms
    setTimeout(() => {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 700);
  }

})();