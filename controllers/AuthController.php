<?php

namespace Controllers;

use Model\Usuario;
use Model\usuarios_habilidades;
use MVC\Router;

class AuthController
{
    public static function login(Router $router)
    {
        $alertas = [];
        $login = true;

        // Mensaje mostrado después de cambiar correctamente
        // la contraseña temporal.
        if (
            isset($_GET['password_actualizado']) &&
            $_GET['password_actualizado'] === '1'
        ) {
            Usuario::setAlerta(
                'exito',
                'Tu contraseña se actualizó correctamente. '
                    . 'Ahora puedes iniciar sesión con tu nueva contraseña.'
            );
        }

        /*
     * Este mensaje se utilizará si el usuario estaba en el flujo
     * de cambio de contraseña y su cuenta fue deshabilitada.
     */
        if (
            isset($_GET['cuenta_deshabilitada']) &&
            $_GET['cuenta_deshabilitada'] === '1'
        ) {
            Usuario::setAlerta(
                'error',
                'Tu cuenta se encuentra deshabilitada. '
                    . 'Comunícate con el administrador de SkillView.'
            );
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Este objeto contiene solamente las credenciales
            // escritas en el formulario.
            $credenciales = new Usuario($_POST);

            $alertas = $credenciales->validarLogin();

            if (empty($alertas)) {

                // Buscar el usuario por correo
                $usuario = Usuario::where(
                    'correo',
                    $credenciales->correo
                );

                if (!$usuario) {
                    Usuario::setAlerta(
                        'error',
                        'El usuario no existe'
                    );
                } elseif (
                    !password_verify(
                        $_POST['password'],
                        $usuario->password
                    )
                ) {
                    Usuario::setAlerta(
                        'error',
                        'Contraseña incorrecta'
                    );
                } elseif ((int) $usuario->habilitado !== 1) {
                    /*
                 * Aunque la contraseña sea correcta, un usuario
                 * deshabilitado no puede obtener una sesión normal
                 * ni una sesión temporal para cambiar la contraseña.
                 */
                    Usuario::setAlerta(
                        'error',
                        'Tu cuenta se encuentra deshabilitada. '
                            . 'Comunícate con el administrador de SkillView.'
                    );
                } else {
                    // Iniciar la sesión para cualquier tipo de ingreso:
                    // normal o con cambio obligatorio de contraseña.
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }

                    // Limpiar cualquier información anterior
                    $_SESSION = [];

                    // Evitar reutilización del identificador de sesión
                    session_regenerate_id(true);

                    /*
                 * Si la contraseña fue restablecida por el administrador,
                 * se crea únicamente una sesión temporal.
                 */
                    if ((int) $usuario->debe_cambiar_password === 1) {

                        $_SESSION['cambio_password_usuario_id'] =
                            (int) $usuario->id;

                        header('Location: /cambiar-password');
                        exit;
                    }

                    // Sesión normal
                    $_SESSION['id'] = $usuario->id;
                    $_SESSION['nombres'] = $usuario->nombres;
                    $_SESSION['apellidos'] = $usuario->apellidos;
                    $_SESSION['edad'] = $usuario->edad;
                    $_SESSION['sexo'] = $usuario->sexo;
                    $_SESSION['correo'] = $usuario->correo;
                    $_SESSION['universidad'] = $usuario->universidad;
                    $_SESSION['carrera'] = $usuario->carrera;
                    $_SESSION['admin'] = $usuario->admin ?? null;

                    // Redireccionar según el rol
                    if ((int) $usuario->admin === 1) {
                        header('Location: /admin/dashboard');
                    } else {
                        header('Location: /principal');
                    }

                    exit;
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'alertas' => $alertas,
            'login' => $login
        ]);
    }

    public static function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            session_start();
            $_SESSION = [];
            header('Location: /');
        }
    }

    public static function registro(Router $router)
    {
        $login = true;
        $usuario = new Usuario;
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Sincronizar datos enviados por POST
            $usuario->sincronizar($_POST);

            /*
            * Forzar los valores administrativos del usuario.
            * No se toman desde la petición enviada por el navegador.
            */
            $usuario->admin = 0;
            $usuario->habilitado = 1;
            $usuario->debe_cambiar_password = 0;

            // Validar datos
            $alertas = $usuario->validar_cuenta();

            if (empty($alertas)) {

                // Verificar si el correo ya está registrado
                $existeUsuario = Usuario::where('correo', $usuario->correo);

                if ($existeUsuario) {
                    Usuario::setAlerta('error', 'El Usuario ya esta registrado');
                } else {
                    // Hash al password
                    $usuario->hashPassword();
                    // Eliminar password2
                    unset($usuario->password2);
                    // Guardar nuevo usuario
                    $resultado =  $usuario->guardar();

                    if ($resultado) {
                        // IMPORTANTE:
                        // Al crear un usuario, necesitamos inicializar su progreso por habilidad en usuarios_habilidades.
                        // Esto evita que el perfil muestre resultados extraños (promedios incompletos, filas faltantes, etc.).
                        // Se insertan todas las habilidades habilitadas con:
                        // nivel=1, progreso=0 y ultima_actualizacion=fecha actual (o fecha de creación del usuario).
                        $idUsuario = (int)($resultado['id'] ?? $usuario->id ?? 0);

                        if ($idUsuario > 0) {
                            usuarios_habilidades::inicializarHabilidadesUsuario($idUsuario);
                        }

                        // Iniciar sesión automáticamente (autologin)
                        if (!isset($_SESSION)) {
                            session_start();
                        }

                        $_SESSION['id'] = $idUsuario;
                        $_SESSION['nombres'] = $usuario->nombres;
                        $_SESSION['apellidos'] = $usuario->apellidos;
                        $_SESSION['edad'] = $usuario->edad;
                        $_SESSION['sexo'] = $usuario->sexo;
                        $_SESSION['correo'] = $usuario->correo;
                        $_SESSION['universidad'] = $usuario->universidad;
                        $_SESSION['carrera'] = $usuario->carrera;
                        $_SESSION['admin'] = $usuario->admin ?? null;

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

    public static function recuperarPassword(Router $router)
    {
        $login = true;
        $alertas = [];
        $mensajeRecuperacion = null;
        $correo = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $correo = strtolower(trim($_POST['correo'] ?? ''));

            if (!$correo) {
                $alertas['error'][] = 'El correo es obligatorio';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $alertas['error'][] =
                    'Ingresa una dirección de correo electrónico válida';
            } else {
                /*
             * Por seguridad es preferible mostrar un mensaje genérico,
             * exista o no exista el correo.
             */
                $mensajeRecuperacion = 'Si el correo ingresado se encuentra registrado en '
                    . 'SkillView, comunícate con el administrador mediante '
                    . 'admin@skillview.com para solicitar el restablecimiento '
                    . 'de tu contraseña.';
            }
        }

        $router->render('auth/recuperar-password', [
            'titulo' => 'Recuperar contraseña',
            'login' => $login,
            'alertas' => $alertas,
            'mensajeRecuperacion' => $mensajeRecuperacion,
            'correo' => $correo
        ]);
    }

    public static function cambiarPassword(Router $router)
    {
        $login = true;
        $alertas = [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Solo se puede acceder con una sesión temporal válida
        $usuarioId = $_SESSION['cambio_password_usuario_id'] ?? null;

        if (!$usuarioId) {
            header('Location: /');
            exit;
        }

        $usuarioId = filter_var($usuarioId, FILTER_VALIDATE_INT);

        if (!$usuarioId) {
            $_SESSION = [];
            session_destroy();

            header('Location: /');
            exit;
        }

        $usuario = Usuario::find((int) $usuarioId);

        // Verificar que el usuario todavía exista
        if (!$usuario) {
            $_SESSION = [];
            session_destroy();

            header('Location: /');
            exit;
        }

        // Verificar que la cuenta continúe habilitada
        if ((int) $usuario->habilitado !== 1) {
            $_SESSION = [];
            session_destroy();

            header('Location: /?cuenta_deshabilitada=1');
            exit;
        }

        // Verificar que todavía deba cambiar la contraseña
        if ((int) $usuario->debe_cambiar_password !== 1) {
            $_SESSION = [];
            session_destroy();

            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Guardamos el hash de la contraseña temporal
            $passwordTemporalHash = $usuario->password;

            // Recibimos la nueva contraseña
            $usuario->password = $_POST['password'] ?? '';
            $usuario->password2 = $_POST['password2'] ?? '';

            $alertas = $usuario->validarCambioPassword();

            /*
         * Evitamos que el usuario escriba como nueva contraseña
         * exactamente la misma contraseña temporal.
         */
            if (
                empty($alertas) &&
                password_verify(
                    $usuario->password,
                    $passwordTemporalHash
                )
            ) {
                $alertas['error'][] =
                    'La nueva contraseña debe ser diferente a la contraseña temporal';
            }

            if (empty($alertas)) {

                // Hashear la nueva contraseña
                $usuario->hashPassword();

                // Ya no será necesario cambiarla en el próximo ingreso
                $usuario->debe_cambiar_password = 0;

                unset($usuario->password2);

                $resultado = $usuario->guardar();

                if ($resultado) {

                    // Eliminamos completamente la sesión temporal
                    $_SESSION = [];
                    session_destroy();

                    header('Location: /?password_actualizado=1');
                    exit;
                }

                $alertas['error'][] =
                    'No fue posible actualizar la contraseña. Intenta nuevamente.';
            }
        }

        $router->render('auth/cambiar-password', [
            'titulo' => 'Crear nueva contraseña',
            'login' => $login,
            'alertas' => $alertas
        ]);
    }
}
