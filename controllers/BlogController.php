<?php

namespace Controllers;

use Model\Blog;
use Model\HabilidadesBlandas;
use MVC\Router;

class BlogController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Leer filtros desde GET (cuando el user cambie selects)
        //¿Existe la clave habilidad dentro de $_GET? y “Existe habilidad y además no está vacío”
        //Si el usuario selecciona "Todas las habilidades" entonces isset($_GET['habilidad']) es true (porque existe), pero $_GET['habilidad'] !== '' es false (porque está vacío)
        $habilidadID = isset($_GET['habilidad']) && $_GET['habilidad'] !== '' ? (int)$_GET['habilidad'] : null;
        // 1) Blogs (Filtrados o no)
        $blogs = Blog::filtrar($habilidadID);
        // 2) Habilidades para el select (Solo las que tienen blogs)
        $habilidadesFiltro = HabilidadesBlandas::conBlogs();
        // 3) Tags por blog (para mostrar en cada artículo sus habilidades)
        $blogsIds = array_map(fn($b) => (int)$b->id, $blogs);
        $tagsPorBlog = Blog::tagsPorBlogs($blogsIds);

        // Render a la vista 
        $router->render('paginas/blog/blog', [
            'titulo' => 'Explora, aprende y crece con SkillView',
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            //Datos para la vista
            'blogs' => $blogs,
            'habilidadesFiltro' => $habilidadesFiltro,
            'habilidadSeleccionada' => $habilidadID,
            'tagsPorBlog' => $tagsPorBlog
        ]);
    }
}
