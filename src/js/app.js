//Esperamos a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', () => {
  //---------------MODAL DE REGISTRO EXITOSO CON REDIRECCIÓN AUTOMÁTICA----------------//

  //Detectamos si el registro fue exitoso buscando el atributo data-registro-exitoso en el contenedor .register
  const registerContainer = document.querySelector('.register[data-registro-exitoso="1"]');
  const modal = document.getElementById('modal-registro-exitoso');

  // Solo si estamos en la vista de registro exitoso Y existe el modal,
  // ejecutamos la lógica del modal. PERO no hacemos return, para no cortar el resto del código.
  if (registerContainer && modal) {
    const modalContent = modal.querySelector('.modal__content');
    const closeButton = modal.querySelector('[data-modal-close]');
    let redirectUrl = '/principal';
    if (window.location.pathname.startsWith('/admin/usuarios/editar')) {
      redirectUrl = '/admin/usuarios';
    } else if (window.location.pathname.startsWith('/admin/habilidades/crear') || window.location.pathname.startsWith('/admin/habilidades/editar')) {
      redirectUrl = '/admin/habilidades';
    }
    const REDIRECT_DELAY = 5000; // 5 segundos

    // Mostrar el modal
    const showModal = () => {
      //Agregamos la clase modal--visible para mostrar el modal
      modal.classList.add('modal--visible');
    };

    // Cerrar el modal
    const hideModal = () => {
      //Quitamos la clase modal--visible para ocultar el modal
      modal.classList.remove('modal--visible');
    };

    // Mostrar el modal inmediatamente cuando se cargue esta vista
    //Despues de detectar un registro exitoso y verificar que el modal existe entonces...
    showModal();

    // Programar la redirección automática
    const timeoutId = setTimeout(() => {
      window.location.href = redirectUrl;
    }, REDIRECT_DELAY);

    // Si el usuario hace clic en el botón "Ir ahora..."
    if (closeButton) {
      closeButton.addEventListener('click', () => {
        clearTimeout(timeoutId); // Cancelar la redirección automática
        hideModal(); // Ocultar el modal
        window.location.href = redirectUrl;
      });
    }

    // Cerrar al hacer clic fuera del contenido (en el backdrop)
    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.classList.contains('modal__backdrop')) {
        clearTimeout(timeoutId);
        hideModal();
        window.location.href = redirectUrl;
      }
    });

    // Evitar que clics dentro del contenido cierren el modal
    if (modalContent) {
      modalContent.addEventListener('click', (event) => {
        event.stopPropagation();
      });
    }
    /*event.stopPropagation() detiene la propagación del evento hacia arriba en el DOM.
    Si el usuario hace clic sobre el contenido del modal (título, texto, botón)
    ese clic no se propaga al listener que está en modal.addEventListener('click', ...).
    Sin esto:
    Hacer clic en el botón podría contarse también como clic en el fondo y disparar el cierre doble.*/
  }

  //---------------FIN MODAL DE REGISTRO EXITOSO CON REDIRECCIÓN AUTOMÁTICA----------------//

  //---------------MODAL DE LAS CARDS DE LA SECCIÓN APRENDIZAJE----------------//
  const body = document.body;
  // Abrir modal
  //“Escucha cualquier click en TODA la página”. No escuchamos solo cada botón ya que el DOM puede
  //Cambiar dinámicamente, por lo cual esta técnica "event delegation" garantiza que funcione siempre
  document.addEventListener('click', (e) => {
    //¿El elemento en el que hicieron click tiene la clase .js-learning-modal-open
    //O está dentro de un elemento con esa clase?
    const trigger = e.target.closest('.js-learning-modal-open');
    //Si no, salimos con return y no hacemos nada, esto nos ayuda a evitar que cada click en la página
    //Active algo
    if (!trigger) return;
    //Evitamos que el navegador ejecute su acción normal
    e.preventDefault();
    //Leemos el atributo data-learning-modal-id="learning-modal-habilidad-3 (Por ejemplo)"
    const modalId = trigger.dataset.learningModalId;
    //Buscamos el modal en toda la página: document.getElementById("learning-modal-habilidad-3")
    const modal = document.getElementById(modalId);
    //Si no lo encuentra, salimos con return y no hacemos nada
    if (!modal) return;
    //Mostramos el modal en pantalla
    modal.classList.add('learning-modal--visible');
    body.classList.add('no-scroll');
  });

  // Cerrar modal (botón X, botón Volver, backdrop)
  document.addEventListener('click', (e) => {
    //Todo lo que tenga data-modal-close cierra el modal
    //Seleccionamos los objetos del DOM que tiene el atributo data-learning-modal-close
    const closeTrigger = e.target.closest('[data-learning-modal-close]');
    if (!closeTrigger) return;

    const modal = closeTrigger.closest('.learning-modal');
    if (!modal) return;
    //Quitamos las clases que permiten mostrar el modal
    modal.classList.remove('learning-modal--visible');
    body.classList.remove('no-scroll');
  });

  //"El motodo closest busca el elemento padre mas cercano (Incluyendose a si mismo) que coincida con un selector"
  /*<div class="learning-modal">           ← closest(".learning-modal") devuelve este
   <div class="content">
      <button data-learning-modal-close>Volver</button>  ← usuario hace click aquí
   </div>
  </div>
  Al escribir: const modal = closeTrigger.closest('.learning-modal'); JS pregunta:
  ¿El botón tiene la clase .learning-modal? NO
  ¿Su padre <div class="content"> la tiene? NO
  ¿Su abuelo <div class="learning-modal"> la tiene? SI : Nos devuelve el elemento
  
  El método closest es muy útil ya que como tenemos varios modales normalmente tendríamos que identificar
  El modal con id específico para cerrarlo, pero con closest el botón ya "vive" deontro de su propio modal
*/
  //---------------FIN MODAL DE LAS CARDS DE LA SECCIÓN APRENDIZAJE----------------//


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
      document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
      modalChallenges.classList.remove('is-open');
      modalChallenges.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    };

    // Cerrar (X, backdrop, Cancelar)
    //Recorremos todos los botones de cierre y les agregamos el evento click
    closeTriggers.forEach(btn => btn.addEventListener('click', closeModal));

    // Cerrar con ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalChallenges.classList.contains('is-open')) closeModal();
    });

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

  //---------------MODAL DE LAS CARDS DE LA SECCIÓN BLOG----------------//
  const blogModal = document.getElementById('sv-blog-modal');

  if (blogModal) {
    const blogCloseTriggers = blogModal.querySelectorAll('[data-sv-blog-close]');
    const blogTitleEl = blogModal.querySelector('[data-sv-blog-title]');
    const blogDescEl = blogModal.querySelector('[data-sv-blog-desc]');
    const blogContentEl = blogModal.querySelector('[data-sv-blog-content]');
    const blogTagsWrap = blogModal.querySelector('[data-sv-blog-tags]');
    const blogImg = blogModal.querySelector('#sv-blog-modal-img');
    const blogImgWebp = blogModal.querySelector('#sv-blog-modal-img-webp');

    const openBlogModal = () => {
      blogModal.classList.add('is-open');
      blogModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    };

    const closeBlogModal = () => {
      blogModal.classList.remove('is-open');
      blogModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    };

    blogCloseTriggers.forEach(btn => btn.addEventListener('click', closeBlogModal));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && blogModal.classList.contains('is-open')) closeBlogModal();
    });

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-sv-blog-open]');
      if (!btn) return;

      const title = btn.dataset.title || 'Artículo';
      const desc = btn.dataset.desc || '';
      const content = btn.dataset.content || '';
      const tagsStr = btn.dataset.tags || '';
      const imgWebp = btn.dataset.imageWebp || '';
      const imgJpg = btn.dataset.imageJpg || '';

      if (blogTitleEl) blogTitleEl.textContent = title;
      if (blogDescEl) blogDescEl.textContent = desc;

      // Contenido: lo pintamos como texto (seguro). Si luego quieres HTML, lo controlamos.
      if (blogContentEl) blogContentEl.textContent = content;

      // Imagen
      if (blogImgWebp) blogImgWebp.setAttribute('srcset', imgWebp);
      if (blogImg) {
        blogImg.setAttribute('src', imgJpg);
        blogImg.setAttribute('alt', `Imagen del artículo ${title}`);
      }

      // Tags
      if (blogTagsWrap) {
        blogTagsWrap.innerHTML = '';
        const tags = tagsStr.split(',').map(t => t.trim()).filter(Boolean);
        tags.forEach(tag => {
          const span = document.createElement('span');
          span.className = 'sv-blog-modal__tag';
          span.textContent = tag;
          blogTagsWrap.appendChild(span);
        });
      }

      openBlogModal();
    });
  }
  //---------------FIN MODAL DE LAS CARDS DE LA SECCIÓN BLOG----------------//

  //---------------MODAL EDITAR PERFIL----------------//
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.js-profile-modal-open');
    if (!trigger) return;

    e.preventDefault();

    const modalId = trigger.dataset.profileModalId;
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.add('profile-modal--visible');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
  });

  document.addEventListener('click', (e) => {
    const closeTrigger = e.target.closest('[data-profile-modal-close]');
    if (!closeTrigger) return;

    const modal = closeTrigger.closest('.profile-modal');
    if (!modal) return;

    modal.classList.remove('profile-modal--visible');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
  });

  // Cerrar con ESC
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const modal = document.querySelector('.profile-modal.profile-modal--visible');
    if (!modal) return;

    modal.classList.remove('profile-modal--visible');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
  });

  // ---------- Cerrar modal en éxito (cuando venimos de redirect) ----------
  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);

    // Si el perfil se actualizó, nos aseguramos de que el modal esté cerrado
    if (params.get('actualizado') === '1') {
      const modal = document.getElementById('profile-edit-modal');
      if (modal) {
        modal.classList.remove('profile-modal--visible');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('no-scroll');
      }

      // Opcional: limpiar el query param para que al refrescar no vuelva a quedar "actualizado=1"
      // Mantiene la alerta (ya se renderiza) solo en esa carga
      const url = new URL(window.location.href);
      url.searchParams.delete('actualizado');
      window.history.replaceState({}, '', url.toString());
    }
  });

  //---------------FIN MODAL EDITAR PERFIL----------------//


  //---------------OCULTAR ALERTAS DESPUES DE UNOS SEGUNDOS----------------//
  //ESTE CÓDIGO AHORA SIEMPRE SE EJECUTA EN CUALQUIER VISTA

  const alertas = document.querySelectorAll('.alerta');

  if (alertas.length > 0) {
    alertas.forEach(alerta => {
      // Esperamos 4 segundos antes de ocultarla
      setTimeout(() => {
        alerta.classList.add('alerta--ocultar');

        // Después de la animación, la removemos
        setTimeout(() => {
          alerta.remove();
        }, 500); // coincide con la duración del transition en CSS
      }, 4000);
    });
  }
  //---------------FIN OCULTAR ALERTAS DESPUES DE UNOS SEGUNDOS----------------//

  //---------------MENU MOBILE----------------//
  const toggle = document.querySelector('.site-header__toggle');
  const mobileNav = document.querySelector('.site-nav--mobile');
  const closeBtn = document.querySelector('.site-nav__mobile-close');

  if (!toggle || !mobileNav) return;

  const openMenu = () => {
    mobileNav.classList.add('site-nav--mobile-open');
    toggle.setAttribute('aria-expanded', 'true');
  };

  const closeMenu = () => {
    mobileNav.classList.remove('site-nav--mobile-open');
    toggle.setAttribute('aria-expanded', 'false');
  };

  toggle.addEventListener('click', () => {
    const isOpen = mobileNav.classList.contains('site-nav--mobile-open');
    if (isOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeMenu);
  }

  // Opcional: cerrar menú al hacer click en un enlace
  mobileNav.addEventListener('click', (e) => {
    if (e.target.closest('.site-nav__link')) {
      closeMenu();
    }
  });

  //---------------FIN MENU MOBILE----------------//

});

//Mensaje de confirmación con modal personalizado
function confirmDelete(event, message) {
  // 1. Evitamos que el formulario se envíe automáticamente
  event.preventDefault();

  // 2. Obtenemos el formulario que disparó el evento
  const form = event.target;

  // 3. Si ya existe un modal abierto, lo eliminamos para no duplicar
  const existingModal = document.querySelector('.modal-delete');
  if (existingModal) {
    existingModal.remove();
  }

  // 4. Creamos el contenedor del modal
  const modalDelete = document.createElement('div');
  modalDelete.classList.add('modal-delete');
  modalDelete.innerHTML = `
        <div class="modal-delete__backdrop"></div>
        <div class="modal-delete__content" role="dialog" aria-modal="true" aria-labelledby="modal-delete-title">
            <h2 id="modal-delete-title" class="modal-delete__title">Confirmar eliminación</h2>
            <p class="modal-delete__text">
                ${message || "¿Estás seguro de eliminar este elemento?"}
            </p>
            <div class="modal-delete__actions">
                <button type="button" id="confirm-yes" class="modal-delete__btn--yes">
                    Sí, eliminar
                </button>
                <button type="button" id="confirm-no" class="modal-delete__btn--no">
                    No, cancelar
                </button>
            </div>
        </div>
    `;

  // 5. Lo agregamos al DOM
  document.body.appendChild(modalDelete);

  // 6. Obtenemos los botones
  const btnYes = modalDelete.querySelector('#confirm-yes');
  const btnNo = modalDelete.querySelector('#confirm-no');
  const backdrop = modalDelete.querySelector('.modal-delete__backdrop');

  // Función para cerrar el modal
  const closeModal = () => {
    modalDelete.remove();
  };

  // 7. Si el usuario confirma -> cerramos modal y enviamos el formulario
  btnYes.addEventListener('click', () => {
    closeModal();
    form.submit(); // Aquí sí se envía al servidor
  });

  // 8. Si el usuario cancela -> solo cerramos modal
  btnNo.addEventListener('click', closeModal);

  // 9. Cerrar si hace clic en el fondo oscuro
  backdrop.addEventListener('click', closeModal);

  // 10. Devolvemos false por si acaso, para que el onsubmit no continúe
  return false;
}
