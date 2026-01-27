(function () {
  // Abrir modal
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.js-achievement-modal-open');
    if (!trigger) return;

    e.preventDefault();

    const modalId = trigger.dataset.achievementModalId;
    const modal = document.getElementById(modalId);
    if (!modal) return;

    const icon   = modal.querySelector('#sv-achievement-modal-icon');
    const title  = modal.querySelector('#sv-achievement-modal-title');
    const tag    = modal.querySelector('#sv-achievement-modal-tag');
    const desc   = modal.querySelector('#sv-achievement-modal-desc');
    const status = modal.querySelector('#sv-achievement-modal-status');

    const {
      achievementNombre,
      achievementDescripcion,
      achievementIcono,
      achievementTipo,
      achievementObjetivo,
      achievementDesbloqueado,
      achievementFecha
    } = trigger.dataset;

    if (icon)  icon.src = achievementIcono || '';
    if (title) title.textContent = achievementNombre || '';
    if (tag)   tag.textContent = (achievementTipo || '').toUpperCase();
    if (desc)  desc.textContent = achievementDescripcion || '';

    const unlocked = achievementDesbloqueado === '1';

    if (status) {
      status.classList.toggle('is-locked', !unlocked);

      if (unlocked) {
        status.innerHTML = `
          <p class="achievement-modal__status-label">Desbloqueado</p>
          <p class="achievement-modal__status-date">${achievementFecha || ''}</p>
        `;
      } else {
        status.innerHTML = `
          <p class="achievement-modal__status-label">Bloqueado</p>
          <p class="achievement-modal__status-date">Objetivo: ${achievementObjetivo || ''}</p>
        `;
      }
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');

    if (window.SV && typeof window.SV.lockScroll === 'function') {
      window.SV.lockScroll();
    } else {
      document.body.classList.add('no-scroll');
    }
  });

  // Cerrar modal
  document.addEventListener('click', (e) => {
    const closeTrigger = e.target.closest('[data-achievement-modal-close]');
    if (!closeTrigger) return;

    const modal = closeTrigger.closest('.achievement-modal');
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    if (window.SV && typeof window.SV.unlockScroll === 'function') {
      window.SV.unlockScroll();
    } else {
      document.body.classList.remove('no-scroll');
    }
  });
})();
