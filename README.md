//POR QUÉ USAMOS WHILE EN LUGAR DE consultarSQL?
    //Porque no estamos creando objetos del modelo usuarios_retos, sino devolviendo estructuras personalizadas (arreglos) con columnas que:
    //No existen en tu modelo (ej: completados)
    //Vienen como alias (COUNT(*) AS completados)
    //Además mezclan datos de otras tablas (hb.nombre)

    //consultarSQL() FUNCIONA ASÍ:
    //Ejecuta query
    //Hace fetch_assoc() en un while
    //Por cada fila llama static::crearObjeto($registro)
    //Crea objetos del modelo actual y les asigna propiedades si existen.
    El problema:
    En usuarios_retos, las propiedades son: $id, $id_usuarios, $id_retos, $completado, $fecha_completado, $puntaje_obtenido

    Pero la query de completadosPorHabilidad() trae: id_habilidad, nombre, completados
    Esas propiedades no existen en el objeto usuarios_retos, así que crearObjeto() no puede mapearlas y se perdería información.

✅ Por eso en ese tipo de consultas usamos SQL “manual” + while + fetch_assoc(), y construimos nosotros el array final.
//FIN POR QUÉ USAMOS WHILE EN LUGAR DE consultarSQL

//POR QUÉ EN OTRAS USAMOS consultarSQL()?
    Se usa consultarSQL() cuando:

✅ La consulta devuelve columnas que coinciden con el modelo
✅ Queremos objetos del modelo (Retos, Logros, etc.)
✅ No hay agregaciones raras (COUNT, GROUP BY), o si las hay, igual coinciden con propiedades reales

Ejemplos tuyos:

Retos::habilitadas()

Hace SELECT * FROM retos ...
Eso sí coincide con el modelo Retos → perfecto para consultarSQL().

Logros::destacados(6)

También devuelve filas completas de logros (id,nombre,descripcion,icono,tipo,...)
Eso coincide con el modelo Logros → perfecto.
//FIN POR QUÉ EN OTRAS USAMOS consultarSQL()?