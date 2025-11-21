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
        $alertas = [];
        $login = true;
        $usuario = new Usuario;

        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            $usuario->sincronizar($_POST);
            
            $alertas = $usuario->validar_cuenta();

            if(empty($alertas)) {
                $existeUsuario = Usuario::where('correo', $usuario->correo);

                if($existeUsuario) {
                    Usuario::setAlerta('error', 'El Usuario ya esta registrado');
                    $alertas = Usuario::getAlertas();
                } else {
                    // Hashear el password
                    $usuario->hashPassword();

                    // Eliminar password2
                    unset($usuario->password2);

                    // Crear un nuevo usuario
                    $resultado =  $usuario->guardar();
                    
                    if($resultado) {
                        header('Location: /');
                    }
                }
            }
        }

        // Render a la vista
        $router->render('auth/registro', [
            'titulo' => 'Crea tu cuenta en SkillView',
            'usuario' => $usuario, 
            'alertas' => $alertas,
            'login' => $login
        ]);
    }
}