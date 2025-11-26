<?php

namespace Controllers;

use Classes\Paginacion;
use Model\Usuario;
use MVC\Router;

class DashboardController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        // Render a la vista 
        $router->render('admin/dashboard/index', [
            'titulo' => 'Panel de administración'
        ]);
    }

    public static function indexUsuarios(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        // Obtenemos la búsqueda desde la URL
        $busqueda = $_GET['busqueda'] ?? '';

        // Obtenemos la página desde la URL y verificamos que sea un número y que no sea negativo
        $pagina_actual = $_GET['page'] ?? 1;
        $pagina_actual = filter_var($pagina_actual, FILTER_VALIDATE_INT);

        //Evitamos: URLs mal formadas, Números negativos, Inyecciones tipo page=asdf, Que un usuario manipule la paginación, Que la app rompa al calcular offset
        if (!$pagina_actual || $pagina_actual < 1) {
            // Si hay búsqueda, la mantenemos en la redirección
            //Ya que si el usuario está buscando algo, lo redirigimos sin perder la busqueda
            $url = '/admin/usuarios?page=1';
            if ($busqueda !== '') {
                //urlenconde nos ayuda a codificar caracteres especiales en la URL:
                //urlencode("juan pérez"); = &busqueda=juan+p%C3%A9rez
                $url .= '&busqueda=' . urlencode($busqueda);
            }
            header("Location: {$url}");
            //Detenemos la ejecución del resto del código
            exit;
        }

        $registros_por_pagina = 5;
        $usuarios = [];

        // Si hay búsqueda, usamos métodos especiales con WHERE + LIKE
        if ($busqueda !== '') {

            // Total de registros que cumplen la búsqueda
            $total_registros = Usuario::totalBusquedaUsuarios($busqueda);

            // Extra query para que la paginación mantenga el parámetro busqueda
            //urlenconde nos ayuda a codificar caracteres especiales en la URL:
            //urlencode("juan pérez"); = ?busqueda=juan+p%C3%A9rez
            $extraQuery = 'busqueda=' . urlencode($busqueda);

            // Instanciamos la paginación con el extraQuery
            $paginacion = new Paginacion(
                $pagina_actual,
                $registros_por_pagina,
                $total_registros,
                $extraQuery
            );

            // Redireccionamos si la página actual es mayor al total de páginas
            if ($paginacion->totalPaginas() > 0 && $pagina_actual > $paginacion->totalPaginas()) {
                $url = '/admin/usuarios?page=1&busqueda=' . urlencode($busqueda);
                header("Location: {$url}");
                exit;
            }

            // Traemos los usuarios filtrados y paginados
            $usuarios = Usuario::paginarBusquedaUsuarios(
                $busqueda,
                $registros_por_pagina,
                $paginacion->offset()
            );
        } else {
            // Listado normal sin búsqueda

            $total_registros = Usuario::total();

            $paginacion = new Paginacion(
                $pagina_actual,
                $registros_por_pagina,
                $total_registros
            );

            // Redireccionamos si la página actual es mayor al total de páginas
            if ($paginacion->totalPaginas() > 0 && $pagina_actual > $paginacion->totalPaginas()) {
                header('Location: /admin/usuarios?page=1');
                exit;
            }

            // Traemos los usuarios paginados normalmente
            $usuarios = Usuario::paginar('nombres', $registros_por_pagina, $paginacion->offset());
        }

        // Cambiamos los valores 0 y 1 de la columna sexo por Femenino y Masculino
        if (!empty($usuarios)) {
            foreach ($usuarios as $usuario) {
                $usuario->sexo = $usuario->sexo ? 'Femenino' : 'Masculino';
            }
        }

        // Render a la vista 
        $router->render('admin/usuarios/index', [
            'titulo'     => 'Gestión de Usuarios',
            'usuarios'   => $usuarios,
            'paginacion' => $paginacion->paginacion(),
            'busqueda'   => $busqueda
        ]);
    }

    public static function editarUsuarios(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $alertas = [];
        $alertasExito = [];
        //Validar el id que llega por la URL
        $id = $_GET['id'];
        //Validamos si el id es un número entero
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: /admin/usuarios');
            exit;
        }
        //Obtenemos el usuario a editar
        $usuario = Usuario::find($id);
        //Validamos si el usuario existe
        if (!$usuario) {
            header('Location: /admin/usuarios');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            //Guardamos el hash original antes de sincronizar
            $passwordOriginal = $usuario->password;
            //Sincronizamos con los datos del formulario
            $usuario->sincronizar($_POST);
            //Validamos
            $alertas = $usuario->validar_edicion();
            //Si no hay alertar, guardamos
            if (empty($alertas)) {
                //Validamos si el admin escribió un nuevo password
                if ($usuario->password) {
                    //Si el admin escribió una nueva contraseña...
                    $usuario->hashPassword();
                } else {
                    //Si no escribió nada en password, mantenemos la contraseña original
                    $usuario->password = $passwordOriginal;
                }
                // Eliminar password2
                unset($usuario->password2);
                //Actualizamos el usuarios
                $resultado = $usuario->guardar();

                if ($resultado) {
                    $alertasExito[] = "El usuario de actualizó correctamente";
                } else {
                    $alertas['error'][] = "Ocurrió un error al guardar el usuario";
                }
            }
        }
        // Render a la vista 
        $router->render('admin/usuarios/editar', [
            'titulo' => 'Editar Usuario',
            'alertas' => $alertas,
            'alertasExito' => $alertasExito,
            'usuario' => $usuario
        ]);
    }

    public static function eliminarUsuarios()
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $usuario = Usuario::find($id);
            if (!isset($usuario)) {
                $_SESSION['alertas']['error'][] = "No se pudo eliminar el usuario";
                header('Location: /admin/usuarios');
                exit;
            }
            $resultado = $usuario->eliminar();
            if ($resultado) {
                // Guardamos la alerta en sesión para mostrarla después del redirect
                $_SESSION['alertas']['exito'][] = "El usuario se eliminó correctamente";
                header('Location: /admin/usuarios');
                exit;
            }
        }
    }

    public static function indexHabilidades(Router $router)
    {

        // Render a la vista 
        $router->render('admin/habilidades/index', [
            'titulo' => 'Panel de administración'
        ]);
    }
}
