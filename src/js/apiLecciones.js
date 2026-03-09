(function () {
    let leccionId = 1;
    let habilidadId = 1;
    fetch('/api/lecciones/start', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            lessonId: leccionId,
            skillId: habilidadId
        })
    })
        .then(response => response.json())
        .then(data => {
            console.log('Respuesta /api/lecciones/start:', data);
        })
        .catch(error => {
            console.error('Error al iniciar la lección:', error);
        });

    //Preguntamos al usuario la primera pregunta
    fetch('/api/lecciones/turn', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lessonId: leccionId,
            action: 'advance'
        })
    })
        .then(r => r.json())
        .then(data => console.log(data));

    //Respondemos la pregunta (De manera invalida)
    fetch('/api/lecciones/turn', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lessonId: leccionId,
            action: 'reply',
            message: 'Sí, claro'
        })
    })
        .then(r => r.json())
        .then(data => console.log(data));
    //Respondemos la pregunta (De manera válida)
    fetch('/api/lecciones/turn', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lessonId: leccionId,
            action: 'reply',
            message: 'En un proyecto académico trabajé con mi equipo organizando tareas y ayudando a resolver un problema de comunicación.'
        })
    })
        .then(r => r.json())
        .then(data => console.log(data));
})();