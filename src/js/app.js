//Esperamos a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', () => {
//---------------MODAL DE REGISTRO EXITOSO CON REDIRECCIÓN AUTOMÁTICA----------------//
//Detectamos si el registro fue exitoso buscando el atributo data-registro-exitoso en el contenedor .register
  const registerContainer = document.querySelector('.register[data-registro-exitoso="1"]');
  if (!registerContainer) return; // Si no hubo registro exitoso, no hacemos nada

//Buscamos el modal en el DOM
  const modal = document.getElementById('modal-registro-exitoso');
  if (!modal) return; // Si no se encuentra el modal, no hacemos nada

  const modalContent = modal.querySelector('.modal__content');
  const closeButton = modal.querySelector('[data-modal-close]');
  const redirectUrl = '/principal';
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

//---------------FIN MODAL DE REGISTRO EXITOSO CON REDIRECCIÓN AUTOMÁTICA----------------//
});
