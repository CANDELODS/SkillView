(function () {
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
        //Antes usabamos: body.classList.add('no-scroll');
        //Ahora unificamos con el helper global (app.js): window.SV.lockScroll()
        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            //Fallback por si por alguna razón no cargó app.js primero
            body.classList.add('no-scroll');
        }
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
        //Antes usabamos: body.classList.remove('no-scroll');
        //Ahora unificamos con el helper global (app.js): window.SV.unlockScroll()
        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            //Fallback por si por alguna razón no cargó app.js primero
            body.classList.remove('no-scroll');
        }
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
})();
