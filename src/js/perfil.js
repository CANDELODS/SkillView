(function () {
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
        //Antes usabamos: document.body.classList.add('no-scroll');
        //Ahora unificamos con el helper global (app.js): window.SV.lockScroll()
        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            //Fallback por si por alguna razón no cargó app.js primero
            document.body.classList.add('no-scroll');
        }
    });

    document.addEventListener('click', (e) => {
        const closeTrigger = e.target.closest('[data-profile-modal-close]');
        if (!closeTrigger) return;

        const modal = closeTrigger.closest('.profile-modal');
        if (!modal) return;

        modal.classList.remove('profile-modal--visible');
        modal.setAttribute('aria-hidden', 'true');
        //Antes usabamos: document.body.classList.remove('no-scroll');
        //Ahora unificamos con el helper global (app.js): window.SV.unlockScroll()
        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            //Fallback por si por alguna razón no cargó app.js primero
            document.body.classList.remove('no-scroll');
        }
    });

    // ---------- Cerrar modal en éxito (cuando venimos de redirect) ----------
    //URLSearchParams nos permite trabajar con los query params de la URL.
    //Windows.location.search nos da la parte de query params de la URL. ejemplo /perfil?actualizado=1 -> "?actualizado=1"
    const params = new URLSearchParams(window.location.search);

    // Si el perfil se actualizó, nos aseguramos de que el modal esté cerrado
    //params.get nos devuelve 1 si el query param 'actualizado' existe en la URL, si no existe, devuelve null
    if (params.get('actualizado') === '1') {
        const modal = document.getElementById('profile-edit-modal');
        if (modal) {
            modal.classList.remove('profile-modal--visible');
            modal.setAttribute('aria-hidden', 'true');
            //Unificamos el scroll con helper global
            //Typeof nos sirve para confirmar que esa propiedad existe y que realmente sea una función antes de llamarla
            if (window.SV && typeof window.SV.unlockScroll === 'function') {
                window.SV.unlockScroll();
            } else {
                //Fallback por si por alguna razón no cargó app.js primero
                document.body.classList.remove('no-scroll');
            }
        }

        // Limpiamos la URL para que no vuelva a salir el modal al recargar
        //Usamos el objeto URL completo, esto nos representa toda la URL como un objeto al cual podemos acceder a sus propiedades
        //url.pathname nos da la parte del path de la URL sin los query params = /perfil
        //url.searchParams nos da acceso a los query params para poder manipularlos
        const url = new URL(window.location.href);
        //Eliminamos el parámetro 'actualizado' de los query params
        url.searchParams.delete('actualizado');
        //Cambiamos la URL sin recargar la página
        window.history.replaceState({}, '', url.toString());
        //Usamo replaceState y no pushState para no agregar un nuevo historial en el navegador, sino reemplazar el actual
        //Así evitamos que el usuario pueda volver a la URL con el query param 'actualizado' usando el botón de atrás
    }

    //---------------FIN MODAL EDITAR PERFIL----------------//
})();
