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
    } else if(window.location.pathname.startsWith('/admin/habilidades/crear') || window.location.pathname.startsWith('/admin/habilidades/editar')){
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
