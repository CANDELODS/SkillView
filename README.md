# SkillView – Documentación Técnica del Proyecto

## 📌 Descripción General

**SkillView** es una aplicación web desarrollada en **PHP** bajo una arquitectura **MVC personalizada**, cuyo objetivo es fortalecer las **habilidades blandas** de los usuarios mediante:

- Aprendizaje guiado por lecciones
- Retos gamificados
- Logros y medallas
- Artículos educativos (Blog)
- Panel administrativo

El proyecto está diseñado para ser **escalable, mantenible y optimizado**, evitando duplicación de lógica y reduciendo consultas innecesarias a la base de datos.

---

## 🧱 Arquitectura del Proyecto

### Tecnologías usadas
- PHP (POO)
- MySQL
- JavaScript Vanilla
- SCSS (BEM)
- HTML semántico

### Patrón arquitectónico
- **MVC (Model – View – Controller)**

---

## 🧠 Modelos (Models)

Todos los modelos heredan de una clase base llamada **ActiveRecord**, la cual centraliza la conexión a la base de datos y la lógica común.

### ActiveRecord (núcleo del sistema)

Responsabilidades principales:
- Manejo de la conexión a la base de datos
- CRUD genérico
- Conversión de filas de la BD en objetos PHP
- Métodos reutilizables como:
  - `consultarSQL()`
  - `find()`
  - `where()`
  - `guardar()`
  - `eliminar()`

### ¿Cómo se crean los objetos desde la BD?

Cuando se ejecuta una consulta:

```php
$resultado = self::consultarSQL($query);
Internamente:

MySQL devuelve un cursor

Se recorre con while

Cada fila se convierte en un objeto del modelo correspondiente

php
Copy code
while ($registro = $resultado->fetch_assoc()) {
    $objetos[] = static::crearObjeto($registro);
}
❓ ¿Por qué while y no foreach?
$resultado NO es un array, es un cursor

fetch_assoc() devuelve una fila por vez

El número de filas es desconocido

¿Cuándo usar consultarSQL y cuándo no?
✅ Se usa consultarSQL cuando:

Se necesitan objetos completos

Se van a mostrar datos en vistas

Se requiere reutilizar ActiveRecord

❌ No se usa cuando:

Solo se necesitan IDs

Solo se necesita un COUNT

Se busca máximo rendimiento

Ejemplo correcto:

php
Copy code
usuarios_retos::idsRetosCompletados();
📚 Modelos importantes
Usuario
Maneja validaciones de login, registro y edición

Hasheo y verificación de contraseñas

Búsquedas y paginación

Lecciones
Obtiene la lección actual según usuario y habilidad

Usa LEFT JOIN para detectar lecciones no completadas

Retos
Filtrado por habilidad y dificultad

Totales por habilidad

Optimización evitando N+1 queries

usuarios_retos
Maneja progreso del usuario

Devuelve IDs para crear lookups

Usa array_flip para búsquedas O(1)

usuarios_logros
Maneja las medallas desbloqueadas

Devuelve solo IDs de logros

Blog y blog_habilidades
Relación muchos a muchos

Permite que un artículo tenga varias habilidades asociadas

🎯 Controladores
Principio general
Los controladores:

Validan autenticación

Llaman modelos

Preparan datos procesados

Renderizan vistas

RetosController (el más complejo)
Responsabilidades:

Progreso general

Progreso por habilidad

Filtros combinables

Logros desbloqueados

Preparación de datos para cards y modales

Uso de lookups
php
Copy code
$lookup = array_flip($ids);
isset($lookup[$id]);
✔ Más rápido que in_array()
✔ Búsqueda O(1)

BlogController
Filtra artículos por habilidad

Prepara tags y contenido para modales

AprendizajeController
Controla el flujo de lecciones

Calcula progreso de aprendizaje

🧭 Router
El router:

Registra rutas GET y POST

Ejecuta el controlador correspondiente

Redirige a 404 si no existe la ruta

php
Copy code
call_user_func($fn, $this);
Renderizado
Usa buffer de salida

Decide layout según URL (/admin o público)

🧩 Funciones globales
isAuth()
Inicia sesión si no existe

Verifica si el usuario está autenticado

isset()
Se usa para:

Validar filtros GET

Evitar errores por índices inexistentes

⚙️ JavaScript (JS)
app.js
Contiene toda la lógica frontend:

Funcionalidades:
Modales de:

Registro exitoso

Aprendizaje

Retos

Blog

Delegación de eventos

Menú mobile

Ocultamiento automático de alertas

Modal de confirmación personalizada para eliminar

Técnicas usadas:
event delegation

closest()

preventDefault()

dataset

stopPropagation()

👉 Esto permite que los modales funcionen incluso con contenido dinámico.

tags.js
Manejo dinámico de etiquetas (tags):

IIFE para encapsular variables

Uso de:

split

map

filter

Sincronización con input hidden

js
Copy code
tagsInputHidden.value = tags.toString();
Permite:

Agregar tags con coma

Eliminar con doble clic

Evitar tags vacíos

🧠 Buenas prácticas aplicadas
Separación clara de responsabilidades

Optimización de consultas

Uso de lookups

Código reutilizable

Escalabilidad

JS desacoplado del backend

✅ Conclusión
SkillView es un proyecto:

Bien estructurado

Optimizado

Fácil de mantener

Alineado con buenas prácticas profesionales

Apto para presentación académica y portafolio

Esta documentación permite que cualquier desarrollador nuevo entienda el funcionamiento completo del sistema.