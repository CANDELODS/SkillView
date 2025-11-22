<?php

namespace Controllers;

use Classes\Paginacion;
use Model\Usuario;
use MVC\Router;

class DashboardController {
    public static function index(Router $router) {

        // Render a la vista 
        $router->render('admin/dashboard/index', [
            'titulo' => 'Panel de administración'
        ]);
    }

    public static function indexUsuarios(Router $router) {
        //Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
        }
        //PAGINAR
        //Obtenemos la página desde la URL y verificamos que sea un número y que no sea negativo
        $pagina_actual = $_GET['page'];
        $pagina_actual = filter_var($pagina_actual, FILTER_VALIDATE_INT);
        //La función filter var devuelve un boolean, por lo cual
        //Si devuelve false no pasará la validación, igualmente si
        //El número es negativo
        if (!$pagina_actual || $pagina_actual < 1) {
            header('Location: /admin/usuarios?page=1');
        }
        $registros_por_pagina = 8;
        $total_registros = Usuario::total();
        $paginacion = new Paginacion($pagina_actual, $registros_por_pagina, $total_registros);
        //Instanciamos el modelo de usuario
        $usuarios = new Usuario;
        //Traemos todos los usuarios
        $usuarios = Usuario::paginar('nombres', $registros_por_pagina, $paginacion->offset());
        //Redireccionamos si la página actual es mayor al total de páginas
        if ($paginacion->totalPaginas() > 0 && $pagina_actual > $paginacion->totalPaginas()) {
            header('Location: /admin/usuarios?page=1');
            exit;
        }
        //Cambiamos los valores 0 y 1 de la columna sexo por femenino y masculino
        if (!empty($usuarios)) {
            foreach ($usuarios as $usuario) {
                //Verificamos si hay algo en el atributo local y nube de nuestro objeto,
                //Si hay algo, lo convertimos a un string para mostrarlo en la vista
                $usuario->sexo = $usuario->sexo ? 'Masculino' : 'Femenino';
            }
        }
        // Render a la vista 
        $router->render('admin/usuarios/index', [
            'titulo' => 'Panel de administración',
            'usuarios' => $usuarios,
            'paginacion' => $paginacion->paginacion()
        ]);
    }

    public static function editarUsuarios(Router $router) {

        // Render a la vista 
        $router->render('admin/usuarios/editar', [
            'titulo' => 'Editar Usuario'
        ]);
    }

    public static function eliminarUsuarios(Router $router) {

        // Render a la vista 
        $router->render('admin/usuarios/eliminar', [
            'titulo' => 'Editar Usuario'
        ]);
    }

    public static function indexHabilidades(Router $router) {

        // Render a la vista 
        $router->render('admin/habilidades/index', [
            'titulo' => 'Panel de administración'
        ]);
    }
}