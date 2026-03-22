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

    // Buscamos el modal de logros en el DOM.
    // Este es el modal que se abrirá automáticamente cuando existan logros recientes en window.logrosRecientes.
    const achievementModal = document.getElementById('sv-achievement-modal');

    // Solo ejecutamos esta lógica si:
    // 1. existe el modal en la vista,
    // 2. window.logrosRecientes es un array,
    // 3. el array tiene al menos un logro.
    if (achievementModal && Array.isArray(window.logrosRecientes) && window.logrosRecientes.length > 0) {
        // Referencias a los elementos internos del modal donde se pintará la información del logro.
        const achievementIcon = achievementModal.querySelector('#sv-achievement-modal-icon');
        const achievementTitle = achievementModal.querySelector('#sv-achievement-modal-title');
        const achievementTag = achievementModal.querySelector('#sv-achievement-modal-tag');
        const achievementDesc = achievementModal.querySelector('#sv-achievement-modal-desc');
        const achievementStatus = achievementModal.querySelector('#sv-achievement-modal-status');

        // Creamos una cola (queue) con los logros recientes.
        // Se usa el operador spread para copiar el arreglo y no modificar directamente window.logrosRecientes.
        // Esto nos permite mostrar uno por uno en orden.
        const queue = [...window.logrosRecientes];

        // Variable para guardar el logro actualmente mostrado en el modal.
        // Es útil si después quieres ampliar lógica relacionada con el logro activo.
        let currentAchievement = null;

        // Función que traduce el tipo numérico del logro a un texto legible.
        // Por ejemplo:
        // 1 -> Habilidad
        // 2 -> Puntaje
        // 3 -> Retos
        // 4 -> Desempeño
        const traducirTipo = (tipo) => {
            const tipos = {
                1: 'Habilidad',
                2: 'Puntaje',
                3: 'Retos',
                4: 'Desempeño'
            };
            return tipos[Number(tipo)] || 'Logro';
        };

        // Función que llena el contenido visual del modal con la información de un logro.
        const fillAchievementModal = (logro) => {
            // Guardamos el logro actual en memoria.
            currentAchievement = logro;

            // Si existe el elemento de imagen, construimos la ruta del ícono del logro.
            // El nombre del ícono viene desde backend, por ejemplo "logro-autoconfianza".
            if (achievementIcon) {
                achievementIcon.src = `${window.location.origin}/build/img/${logro.icono}.svg`;
            }

            // Insertamos el nombre del logro.
            if (achievementTitle) {
                achievementTitle.textContent = logro.nombre || '';
            }

            // Insertamos la etiqueta/tipo traducido del logro.
            if (achievementTag) {
                achievementTag.textContent = traducirTipo(logro.tipo);
            }

            // Insertamos la descripción del logro.
            if (achievementDesc) {
                achievementDesc.textContent = logro.descripcion || '';
            }

            // Insertamos el estado visual del logro.
            // En este caso siempre será "Desbloqueado" porque este modal solo muestra logros recién obtenidos.
            if (achievementStatus) {
                // Por si el elemento tenía clases previas, eliminamos el estado de bloqueado.
                achievementStatus.classList.remove('is-locked');

                // Pintamos estado + fecha de obtención.
                achievementStatus.innerHTML = `
          <p class="achievement-modal__status-label">Desbloqueado</p>
          <p class="achievement-modal__status-date">${logro.fecha_obtenido || ''}</p>
        `;
            }
        };

        // Función que abre el modal de logro.
        const openAchievementModal = () => {
            // Si ya no quedan logros en la cola, no hace nada.
            if (!queue.length) return;

            // Toma el siguiente logro de la cola.
            // shift() lo saca del inicio del arreglo, así se muestran secuencialmente.
            const nextAchievement = queue.shift();

            // Carga la información del logro en el modal.
            fillAchievementModal(nextAchievement);

            // Muestra el modal.
            achievementModal.classList.add('is-open');
            achievementModal.setAttribute('aria-hidden', 'false');

            // Bloquea el scroll del body para que el usuario no pueda mover la página con el modal abierto.
            // Si existe el helper global de app.js, se usa.
            if (window.SV && typeof window.SV.lockScroll === 'function') {
                window.SV.lockScroll();
            } else {
                // Fallback por si el helper global no estuviera cargado.
                document.body.classList.add('no-scroll');
            }
        };

        // Función que cierra el modal actual.
        const closeAchievementModal = () => {
            // Oculta el modal.
            achievementModal.classList.remove('is-open');
            achievementModal.setAttribute('aria-hidden', 'true');

            // Libera el scroll del body.
            if (window.SV && typeof window.SV.unlockScroll === 'function') {
                window.SV.unlockScroll();
            } else {
                document.body.classList.remove('no-scroll');
            }

            // Limpiamos la referencia del logro actual.
            currentAchievement = null;

            // Si todavía quedan logros por mostrar,
            // se abre automáticamente el siguiente después de una pequeña pausa.
            if (queue.length > 0) {
                setTimeout(() => {
                    openAchievementModal();
                }, 250);
            }
        };

        // Listener global para cerrar el modal.
        // Se activa cuando el usuario hace clic en cualquier elemento con data-achievement-modal-close,
        // por ejemplo:
        // - botón "Continuar"
        // - botón "X"
        // - backdrop
        document.addEventListener('click', (e) => {
            const closeTrigger = e.target.closest('[data-achievement-modal-close]');

            // Si el clic no fue en un elemento de cierre, no hace nada.
            if (!closeTrigger) return;

            // Si el modal no está abierto, tampoco hace nada.
            if (!achievementModal.classList.contains('is-open')) return;

            // Cierra el modal actual.
            closeAchievementModal();
        });

        // Al cargar la página, se abre automáticamente el primer logro de la cola.
        openAchievementModal();
    }
    //---------------FIN MODAL AUTOMÁTICO DE LOGROS RECIENTES----------------//
})();
