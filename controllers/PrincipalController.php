<?php

namespace Controllers;

use Model\Usuario;
use MVC\Router;

class PrincipalController {
    public static function index(Router $router) {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;
        // Valores por defecto (por si no hay sesión)
        $nombreUsuario   = 'Juan Candelo';
        $inicialesUsuario = 'JC';

        if (isset($_SESSION['id'])){
            $usuario = Usuario::find($_SESSION['id']);
            if($usuario){
                // Tomamos nombres y apellidos desde la BD
                $nombres   = trim($usuario->nombres ?? '');
                $apellidos = trim($usuario->apellidos ?? '');
                // preg_split divide un string en partes por medio de una expresión regular como separador
                //Soporta tabulaciones, saltos de linea y más. /\s+/ = Cualquier espacio en blanco (Espacio normal, tab, salto de línea, etc)
                //+ = Uno o más. "Juan Sebastian" -> ["Juan", "Sebastian"]
                $nParts = preg_split('/\s+/', $nombres);
                $aParts = preg_split('/\s+/', $apellidos);
                //Obtenemos la primer parte del arreglo
                $primerNombre   = $nParts[0] ?? '';
                $primerApellido = $aParts[0] ?? '';

                // Nombre corto: "PrimerNombre PrimerApellido"
                $nombreCorto = trim($primerNombre . ' ' . $primerApellido);
                if ($nombreCorto !== '') {
                    $nombreUsuario = $nombreCorto;
                }
                // Iniciales usando mb_substr por si hay acentos
                //Además nos devuelve el tecto en MAYUSCULA
                $inicialesUsuario = mb_strtoupper(
                    //mb_substr: Toma la primera letra del texto sin importar si tiene acentos o no.
                    mb_substr($primerNombre, 0, 1, 'UTF-8') .
                    mb_substr($primerApellido, 0, 1, 'UTF-8'),
                    'UTF-8'
                );
            }
        }
        // Render a la vista 
        $router->render('paginas/principal', [
            'titulo' => 'SkillView',
            'login' => $login,
            'nombreUsuario'    => $nombreUsuario,
            'inicialesUsuario' => $inicialesUsuario
        ]);
    }

    public static function notFound(Router $router) {
        $login = true;
        // Render a la vista 
        $router->render('paginas/404', [
            'titulo' => 'Página No Encontrada',
            'login' => $login
        ]);
    }
}