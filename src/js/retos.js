(function () {
    //---------------MODAL DE LAS CARDS DE LA SECCIÓN RETOS----------------//
    const modalChallenges = document.getElementById('sv-challenge-modal');
    if (modalChallenges) {
        const closeTriggers = modalChallenges.querySelectorAll('[data-sv-challenge-close]');
        const titleEl = modalChallenges.querySelector('[data-sv-challenge-title]') || modalChallenges.querySelector('#sv-challenge-modal-title');
        const badgeEl = modalChallenges.querySelector('[data-sv-challenge-badge]');
        const descEl = modalChallenges.querySelector('[data-sv-challenge-desc]');
        const consisteEl = modalChallenges.querySelector('[data-sv-challenge-consiste]');
        const tagsWrap = modalChallenges.querySelector('[data-sv-challenge-tags]');
        const timeEl = modalChallenges.querySelector('[data-sv-challenge-time]');
        const pointsEl = modalChallenges.querySelector('[data-sv-challenge-points]');
        const startBtn = modalChallenges.querySelector('[data-sv-challenge-start]');

        const openModal = () => {
            //Agregamos la clase para mostrar el modal
            modalChallenges.classList.add('is-open');
            //Cambiamos la accesibilidad
            modalChallenges.setAttribute('aria-hidden', 'false');
            //Bloqueamos el scroll del body
            //Antes usabamos: document.body.style.overflow = 'hidden';
            //Ahora unificamos con el helper global (app.js): window.SV.lockScroll()
            if (window.SV && typeof window.SV.lockScroll === 'function') {
                window.SV.lockScroll();
            } else {
                //Fallback por si por alguna razón no cargó app.js primero
                document.body.classList.add('no-scroll');
            }
        };

        const closeModal = () => {
            modalChallenges.classList.remove('is-open');
            modalChallenges.setAttribute('aria-hidden', 'true');
            //Antes usabamos: document.body.style.overflow = '';
            //Ahora unificamos con el helper global (app.js): window.SV.unlockScroll()
            if (window.SV && typeof window.SV.unlockScroll === 'function') {
                window.SV.unlockScroll();
            } else {
                //Fallback por si por alguna razón no cargó app.js primero
                document.body.classList.remove('no-scroll');
            }
        };

        // Cerrar (X, backdrop, Cancelar)
        //Recorremos todos los botones de cierre y les agregamos el evento click
        closeTriggers.forEach(btn => btn.addEventListener('click', closeModal));

        // Abrir desde cualquier card
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-sv-challenge-open]');
            if (!btn) return;

            const title = btn.dataset.title || 'Reto';
            const desc = btn.dataset.desc || '';
            const difficulty = btn.dataset.difficulty || '';
            const timeMin = btn.dataset.timeMin || '';
            const timeMax = btn.dataset.timeMax || '';
            const points = btn.dataset.points || '';
            const tagsStr = btn.dataset.tags || '';
            const startUrl = btn.dataset.startUrl || '#';

            // Pintar contenido
            if (titleEl) titleEl.textContent = title;
            if (badgeEl) badgeEl.textContent = difficulty;
            if (descEl) descEl.textContent = desc;

            // Si no tienes "consiste" por BD aún, reutilizamos desc o un texto base
            if (consisteEl) {
                consisteEl.textContent = 'Este reto te presentará una situación realista donde deberás aplicar tus habilidades blandas. Responderás preguntas y tomarás decisiones que serán evaluadas para medir tu desempeño.';
            }

            // Tags
            if (tagsWrap) {
                tagsWrap.innerHTML = '';
                //Split separa el string en un array usando la coma como separador, con map le quitamos espacios y con filter(Boolean) eliminamos elementos vacíos
                const tags = tagsStr.split(',').map(t => t.trim()).filter(Boolean);
                tags.forEach(tag => {
                    const p = document.createElement('p');
                    p.className = 'sv-challenge-modal__tag';
                    p.textContent = tag;
                    tagsWrap.appendChild(p);
                });
            }

            if (timeEl) timeEl.textContent = `${timeMin}-${timeMax} minutos`;
            if (pointsEl) pointsEl.textContent = `${points} puntos`;
            if (startBtn) startBtn.setAttribute('href', startUrl);
            //Cargamos todo y luego abrimos el modal
            openModal();
        });
    }
    //---------------FIN MODAL DE LAS CARDS DE LA SECCIÓN RETOS----------------//

    //---------------MODAL AUTOMÁTICO DE LOGROS RECIENTES----------------//
    const achievementModal = document.getElementById('sv-achievement-modal');

    if (achievementModal && Array.isArray(window.logrosRecientes) && window.logrosRecientes.length > 0) {
        const achievementIcon = achievementModal.querySelector('#sv-achievement-modal-icon');
        const achievementTitle = achievementModal.querySelector('#sv-achievement-modal-title');
        const achievementTag = achievementModal.querySelector('#sv-achievement-modal-tag');
        const achievementDesc = achievementModal.querySelector('#sv-achievement-modal-desc');
        const achievementStatus = achievementModal.querySelector('#sv-achievement-modal-status');

        const queue = [...window.logrosRecientes];
        let currentAchievement = null;

        const traducirTipo = (tipo) => {
            const tipos = {
                1: 'Habilidad',
                2: 'Puntaje',
                3: 'Retos',
                4: 'Desempeño'
            };
            return tipos[Number(tipo)] || 'Logro';
        };

        const fillAchievementModal = (logro) => {
            currentAchievement = logro;

            if (achievementIcon) {
                achievementIcon.src = `${window.location.origin}/build/img/${logro.icono}.svg`;
            }

            if (achievementTitle) {
                achievementTitle.textContent = logro.nombre || '';
            }

            if (achievementTag) {
                achievementTag.textContent = traducirTipo(logro.tipo);
            }

            if (achievementDesc) {
                achievementDesc.textContent = logro.descripcion || '';
            }

            if (achievementStatus) {
                achievementStatus.classList.remove('is-locked');
                achievementStatus.innerHTML = `
          <p class="achievement-modal__status-label">Desbloqueado</p>
          <p class="achievement-modal__status-date">${logro.fecha_obtenido || ''}</p>
        `;
            }
        };

        const openAchievementModal = () => {
            if (!queue.length) return;

            const nextAchievement = queue.shift();
            fillAchievementModal(nextAchievement);

            achievementModal.classList.add('is-open');
            achievementModal.setAttribute('aria-hidden', 'false');

            if (window.SV && typeof window.SV.lockScroll === 'function') {
                window.SV.lockScroll();
            } else {
                document.body.classList.add('no-scroll');
            }
        };

        const closeAchievementModal = () => {
            achievementModal.classList.remove('is-open');
            achievementModal.setAttribute('aria-hidden', 'true');

            if (window.SV && typeof window.SV.unlockScroll === 'function') {
                window.SV.unlockScroll();
            } else {
                document.body.classList.remove('no-scroll');
            }

            currentAchievement = null;

            if (queue.length > 0) {
                setTimeout(() => {
                    openAchievementModal();
                }, 250);
            }
        };

        document.addEventListener('click', (e) => {
            const closeTrigger = e.target.closest('[data-achievement-modal-close]');
            if (!closeTrigger) return;

            if (!achievementModal.classList.contains('is-open')) return;

            closeAchievementModal();
        });

        openAchievementModal();
    }
    //---------------FIN MODAL AUTOMÁTICO DE LOGROS RECIENTES----------------//
})();
