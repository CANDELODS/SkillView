<?php

namespace Controllers;

use Classes\correo;
use Model\Usuario;
use MVC\Router;

class AuthController {
    public static function login(Router $router) {

        $alertas = [];
        $login = true;

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            $usuario = new Usuario($_POST);

            $alertas = $usuario->validarLogin();
            
            if(empty($alertas)) {
                // Verificar quel el usuario exista
                $usuario = Usuario::where('correo', $usuario->correo);
                if(!$usuario) {
                    Usuario::setAlerta('error', 'El Usuario No Existe');
                } else {
                    // El Usuario existe
                    if( password_verify($_POST['password'], $usuario->password) ) {
                        
                        // Iniciar la sesión
                        session_start();    
                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombres'] = $usuario->nombres;
                        $_SESSION['apellidos'] = $usuario->apellidos;
                        $_SESSION['edad'] = $usuario->edad;
                        $_SESSION['sexo'] = $usuario->sexo;
                        $_SESSION['correo'] = $usuario->correo;
                        $_SESSION['universidad'] = $usuario->universidad;
                        $_SESSION['carrera'] = $usuario->carrera;
                        $_SESSION['admin'] = $usuario->admin ?? null;

                        //Redireccionar
                        if($usuario->admin){
                            header('location: /admin/dashboard');
                        }else{
                            header('location: /principal');
                        }
                        
                    } else {
                        Usuario::setAlerta('error', 'Contraseña Incorrecta');
                    }
                }
            }
        }

        $alertas = Usuario::getAlertas();
        
        // Render a la vista 
        $router->render('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'alertas' => $alertas,
            'login' => $login
        ]);
    }

    public static function logout() {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            session_start();
            $_SESSION = [];
            header('Location: /');
        }
       
    }

    public static function registro(Router $router) {
    $login = true;
    $usuario = new Usuario;
    $alertas = [];

    if($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Sincronizar datos enviados por POST
        $usuario->sincronizar($_POST);
        
        // Validar datos
        $alertas = $usuario->validar_cuenta();

        if(empty($alertas)) {

            // Verificar si el correo ya está registrado
            $existeUsuario = Usuario::where('correo', $usuario->correo);

            if($existeUsuario) {
                Usuario::setAlerta('error', 'El Usuario ya esta registrado');
            } else {
                // Hash al password
                $usuario->hashPassword();
                // Eliminar password2
                unset($usuario->password2);
                // Guardar nuevo usuario
                $resultado =  $usuario->guardar();
                
                if($resultado) {
                    // Creamos alerta de éxito (para el modal)
                    Usuario::setAlerta(
                        'exito',
                        'La cuenta se creó correctamente. Serás redirigido a la página principal en unos segundos.'
                    );
                }
            }
        }
    }

    // Obtenemos todas las alertas (incluyendo 'exito')
    $alertas = Usuario::getAlertas();

    // Guardamos las alertas de éxito en una variable aparte
    $alertasExito = $alertas['exito'] ?? [];

    // Creamos una copia para la vista sin las alertas de éxito
    $alertasVista = $alertas;
    if (isset($alertasVista['exito'])) {
        unset($alertasVista['exito']);
    }

    // Renderizar vista
    $router->render('auth/registro', [
        'titulo'       => 'Crea tu cuenta en SkillView',
        'usuario'      => $usuario, 
        'alertas'      => $alertasVista,   // para alertas.php (sin 'exito')
        'alertasExito' => $alertasExito,   // solo éxito, para el modal
        'login'        => $login
    ]);
}

}