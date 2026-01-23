//Función IFEE, se llama inmediatamente sin tener nombre, además las variables solo existen en este archivo
(function(){
    const tagsInput = document.querySelector('#tags_input');
    if(tagsInput){
        const tagsDiv = document.querySelector('#tags');
        const tagsInputHidden = document.querySelector('[name="tag"]');
        let tags = [];
        //Recuperar del input oculto
        if(tagsInputHidden.value !== ''){
            tags = tagsInputHidden.value.split(',');
            mostrarTags();
        }
        //Escuchar los cambios en el input
        tagsInput.addEventListener('keypress', guardarTag)

        function guardarTag(e){
            //El keyCode Nos Permite Saber El Código De La Tecla Presionada, En Este Caso 44 = coma(,)
            if (e.keyCode === 44) {
                //Para Que No Se Ponga La Coma En Automático Cuando Se Borra El Input Quitamos La Acción Por Defecto Del Form
                e.preventDefault();
                //Prevenir Espacios En Blanco
                if (e.target.value.trim() === '' || e.target.value < 1) {
                    return;
                }
                //Tomamos Una Copia Del Arreglo Tags, Y Reemplazamos El Contenido Con Lo Que Hay En El Input
                tags = [...tags, e.target.value.trim()];
                //Vaciamos El Input Cada Vez Que Se Pone Una Coma(,)
                tagsInput.value = '';
                mostrarTags();
            }
        }

        function mostrarTags(){
            tagsDiv.textContent = '';
            //Iteramos El Arreglo Que Tiene Los Tags, Creo La Variable Momentanea Tag Y Hago Escripting Para
            //Mostrar Los Tags En El Lugar Deseado
            tags.forEach(tag =>{
                const etiqueta = document.createElement('LI');
                etiqueta.classList.add('login__tag');
                etiqueta.textContent = tag;
                //Asignamos un evento y una función a cada Tags conforme es creado
                etiqueta.ondblclick = eliminarTag; //No Ponemos eliminarTag() Por Que Llamaría La Función Inmediatamente
                tagsDiv.appendChild(etiqueta);
            })
            actualizarInputHidden();
        }

        //Como Su Nombre lo Dice, Nos Permitirá Eliminar Los Tags, Se Pasa El Evento (e) Para Identificar El Tag
        function eliminarTag(e){
            //Removemos el Tag del DOM
            e.target.remove();
            //Quitamos El Tag Del Arreglo
            //Filtra o trae todos los tags que no sean al que yo le de click
            tags = tags.filter(tag => tag !== e.target.textContent);
            //Refrescamos Y Sincronizamos El Input Oculto Con Los Nuevos Valores
            actualizarInputHidden();
        }

        //Nos servirá para actualizar el input hidden cuando agregamos y quitamos etiquetas
        function actualizarInputHidden(){
            //Convertimos El Arreglo En Un String
            tagsInputHidden.value = tags.toString();
        }
    }
})(); //Este Parentecis Manda A Llamar A La Función