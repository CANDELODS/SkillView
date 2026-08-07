<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use Model\usuarios_habilidades;
use MVC\Router;

class AuthController
{
    /**
     * Procesa el inicio de sesión y decide el tipo de acceso
     * correspondiente según las credenciales y el estado de la cuenta.
     */
    public static function login(
        Router $router
    ): void {
        $login = true;

        // Mensaje mostrado después de cambiar correctamente
        // una contraseña temporal o recuperada.
        if (
            isset($_GET['password_actualizado'])
            && $_GET['password_actualizado'] === '1'
        ) {
            Usuario::setAlerta(
                'exito',
                'Tu contraseña se actualizó correctamente. '
                    . 'Ahora puedes iniciar sesión con tu nueva contraseña.'
            );
        }

        /*
         * Este mensaje se utiliza cuando el usuario estaba en el
         * flujo de cambio obligatorio y su cuenta fue deshabilitada.
         */
        if (
            isset($_GET['cuenta_deshabilitada'])
            && $_GET['cuenta_deshabilitada'] === '1'
        ) {
            Usuario::setAlerta(
                'error',
                'Tu cuenta se encuentra deshabilitada. '
                    . 'Comunícate con el administrador de SkillView.'
            );
        }

        if (
            $_SERVER['REQUEST_METHOD']
            === 'POST'
        ) {
            /*
             * Usuario::validarLogin() también normaliza el correo
             * mediante trim() y strtolower().
             */
            $credenciales =
                new Usuario(
                    $_POST
                );

            $alertas =
                $credenciales->validarLogin();

            if (empty($alertas)) {
                $resultado =
                    self::evaluarCredenciales(
                        $credenciales->correo,
                        (string) $credenciales->password
                    );

                /*
                 * Los estados rechazados no crean ni modifican
                 * información de sesión.
                 */
                if (!$resultado['autenticado']) {
                    Usuario::setAlerta(
                        'error',
                        $resultado['mensaje']
                    );
                } else {
                    $usuario =
                        $resultado['usuario'];

                    /*
                     * La sesión se crea únicamente después de:
                     * - localizar al usuario;
                     * - verificar la contraseña;
                     * - confirmar que la cuenta está habilitada.
                     */
                    self::iniciarSesionSegura();

                    if (
                        $resultado['requiereCambioPassword']
                    ) {
                        /*
                         * Para el cambio obligatorio se crea una sesión
                         * temporal mínima. No se concede acceso a las
                         * rutas normales ni administrativas.
                         */
                        $_SESSION[
                            'cambio_password_usuario_id'
                        ] = (int) $usuario->id;

                        header(
                            'Location: /cambiar-password'
                        );
                        exit;
                    }

                    // Sesión normal del usuario autenticado.
                    self::guardarDatosSesionUsuario(
                        $usuario
                    );

                    header(
                        'Location: '
                            . $resultado['redireccion']
                    );
                    exit;
                }
            }
        }

        $alertas =
            Usuario::getAlertas();

        $router->render(
            'auth/login',
            [
                'titulo' =>
                    'Iniciar Sesión',
                'alertas' =>
                    $alertas,
                'login' =>
                    $login
            ]
        );
    }

    /**
     * Elimina la sesión autenticada y redirige
     * al formulario de inicio de sesión.
     */
    public static function logout(): void
    {
        /*
         * El cierre de sesión solo se permite mediante POST.
         * Un acceso GET directo no debe destruir una sesión.
         */
        if (
            $_SERVER['REQUEST_METHOD']
            !== 'POST'
        ) {
            header('Location: /404');
            exit;
        }

        if (
            session_status()
            === PHP_SESSION_NONE
        ) {
            session_start();
        }

        // Eliminar todas las variables almacenadas.
        $_SESSION = [];

        /*
         * Eliminar también la cookie que contiene el
         * identificador de la sesión.
         */
        if (ini_get('session.use_cookies')) {
            $parametros =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }

        // Invalidar definitivamente la sesión en el servidor.
        session_destroy();

        header('Location: /');
        exit;
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
            * Un checkbox desmarcado no llega dentro de $_POST.
            * Convertimos expresamente su estado a 1 o 0.
            */
            $usuario->autoriza_tratamiento_datos = isset($_POST['autoriza_tratamiento_datos']) &&
                $_POST['autoriza_tratamiento_datos'] === '1' ? 1 : 0;
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

        $solicitud = new Usuario([
            'correo' => $_POST['correo'] ?? ''
        ]);

        /*
     * Este mensaje se muestra cuando el enlace recibido
     * no existe, expiró o ya fue utilizado.
     */
        if (
            isset($_GET['token_invalido']) &&
            $_GET['token_invalido'] === '1'
        ) {
            $alertas['error'][] =
                'El enlace de recuperación no es válido o ha expirado. '
                . 'Solicita uno nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $alertas = $solicitud->validarCorreo();

            if (empty($alertas)) {

                /*
             * La búsqueda se realiza internamente.
             * La respuesta pública siempre será genérica.
             */
                $usuario = Usuario::buscarPorCorreoRecuperacion(
                    $solicitud->correo
                );

                if (
                    $usuario &&
                    (int) $usuario->habilitado === 1
                ) {
                    /*
                 * Una nueva solicitud reemplaza cualquier token
                 * generado anteriormente.
                 */
                    $tokenPlano =
                        $usuario->crearTokenRecuperacion(30);

                    $resultado = $usuario->guardar();

                    if ($resultado) {
                        $nombreCompleto = trim(
                            $usuario->nombres
                                . ' '
                                . $usuario->apellidos
                        );

                        $email = new Email(
                            $usuario->correo,
                            $nombreCompleto,
                            $tokenPlano
                        );

                        $enviado = $email->enviarRecuperacion();

                        if (!$enviado) {
                            /*
                         * No informamos públicamente del error,
                         * porque revelaría que la cuenta existe.
                         */
                            error_log(
                                'Falló el correo de recuperación '
                                    . 'para el usuario ID '
                                    . (int) $usuario->id
                            );
                        }
                    }
                }

                /*
             * Se muestra exactamente el mismo mensaje:
             * - Si el usuario existe.
             * - Si no existe.
             * - Si está deshabilitado.
             */
                $mensajeRecuperacion =
                    'Si existe una cuenta habilitada asociada al '
                    . 'correo ingresado, recibirás un mensaje con '
                    . 'las instrucciones para restablecer tu contraseña.';
            }
        }

        $router->render('auth/recuperar-password', [
            'titulo' => 'Recuperar contraseña',
            'login' => $login,
            'alertas' => $alertas,
            'mensajeRecuperacion' => $mensajeRecuperacion,
            'correo' => $solicitud->correo
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

    public static function restablecerPassword(Router $router)
    {
        $login = true;
        $alertas = [];

        /*
     * En GET llega por la URL (Primera visita).
     * En POST llega mediante un input oculto (El usuario envía la nueva contraseña).
     */
        $token = trim(
            (string) (
                $_POST['token']
                ?? $_GET['token']
                ?? ''
            )
        );

        // El token original (Generado con bin2hex(random_bytes(32)))
        //Debe contener números y letras entrea y f y tener 64 caracteres.
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            header(
                'Location: /recuperar-password?token_invalido=1'
            );
            exit;
        }

        // Generamos el mismo hash que se almacenó en la BD.
        $tokenHash = hash('sha256', $token);

        $usuario = Usuario::buscarPorTokenRecuperacion(
            $tokenHash
        );

        /*
     * Validar:
     * - Que el token pertenezca a alguien.
     * - Que la cuenta continúe habilitada.
     * - Que el token no haya expirado.
     */
        if (
            !$usuario ||
            (int) $usuario->habilitado !== 1 ||
            !$usuario->tokenRecuperacionVigente()
        ) {
            /*
         * Si encontramos al usuario, invalidamos el token
         * expirado o perteneciente a una cuenta deshabilitada.
         */
            if ($usuario) {
                $usuario->limpiarTokenRecuperacion();
                $usuario->guardar();
            }

            header(
                'Location: /recuperar-password?token_invalido=1'
            );
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $usuario->password =
                $_POST['password'] ?? '';

            $usuario->password2 =
                $_POST['password2'] ?? '';

            $alertas = $usuario->validarCambioPassword();

            if (empty($alertas)) {

                // Guardar la nueva contraseña hasheada.
                $usuario->hashPassword();

                /*
             * El usuario ya estableció su propia contraseña,
             * por lo que no debe cambiarla nuevamente al iniciar.
             */
                $usuario->debe_cambiar_password = 0;

                // El enlace se vuelve inutilizable.
                $usuario->limpiarTokenRecuperacion();

                //Eliminar propiedad no persistente
                unset($usuario->password2);

                $resultado = $usuario->guardar();

                if ($resultado) {
                    header(
                        'Location: /?password_actualizado=1'
                    );
                    exit;
                }

                $alertas['error'][] =
                    'No fue posible actualizar la contraseña. '
                    . 'Intenta nuevamente.';
            }
        }

        $router->render('auth/restablecer-password', [
            'titulo' => 'Restablecer contraseña',
            'login' => $login,
            'alertas' => $alertas,
            'token' => $token
        ]);
    }

    /**
     * Evalúa las credenciales sin modificar la sesión.
     *
     * @return array{
     *     autenticado: bool,
     *     mensaje: string,
     *     usuario: Usuario|null,
     *     requiereCambioPassword: bool,
     *     redireccion: string
     * }
     */
    private static function evaluarCredenciales(
        string $correo,
        string $password
    ): array {
        $usuario =
            Usuario::where(
                'correo',
                $correo
            );

        if (!$usuario) {
            return [
                'autenticado' => false,
                'mensaje' =>
                    'El usuario no existe',
                'usuario' => null,
                'requiereCambioPassword' =>
                    false,
                'redireccion' => '/'
            ];
        }

        if (
            !password_verify(
                $password,
                $usuario->password
            )
        ) {
            return [
                'autenticado' => false,
                'mensaje' =>
                    'Contraseña incorrecta',
                'usuario' => null,
                'requiereCambioPassword' =>
                    false,
                'redireccion' => '/'
            ];
        }

        if (
            (int) $usuario->habilitado
            !== 1
        ) {
            return [
                'autenticado' => false,
                'mensaje' =>
                    'Tu cuenta se encuentra deshabilitada. '
                    . 'Comunícate con el administrador de SkillView.',
                'usuario' => null,
                'requiereCambioPassword' =>
                    false,
                'redireccion' => '/'
            ];
        }

        $requiereCambioPassword =
            (int) $usuario->
                debe_cambiar_password
            === 1;

        if ($requiereCambioPassword) {
            return [
                'autenticado' => true,
                'mensaje' => '',
                'usuario' => $usuario,
                'requiereCambioPassword' =>
                    true,
                'redireccion' =>
                    '/cambiar-password'
            ];
        }

        $redireccion =
            (int) $usuario->admin === 1
                ? '/admin/dashboard'
                : '/principal';

        return [
            'autenticado' => true,
            'mensaje' => '',
            'usuario' => $usuario,
            'requiereCambioPassword' =>
                false,
            'redireccion' =>
                $redireccion
        ];
    }

    /**
     * Inicia una sesión limpia y genera un nuevo identificador.
     *
     * Regenerar el ID después de autenticar evita reutilizar
     * un identificador de sesión previo al inicio de sesión.
     */
    private static function iniciarSesionSegura(): void
    {
        if (
            session_status()
            === PHP_SESSION_NONE
        ) {
            session_start();
        }

        $_SESSION = [];

        session_regenerate_id(
            true
        );
    }

    /**
     * Guarda únicamente los datos necesarios para una sesión normal.
     */
    private static function guardarDatosSesionUsuario(
        Usuario $usuario
    ): void {
        $_SESSION['id'] =
            (int) $usuario->id;

        $_SESSION['nombres'] =
            $usuario->nombres;

        $_SESSION['apellidos'] =
            $usuario->apellidos;

        $_SESSION['edad'] =
            $usuario->edad;

        $_SESSION['sexo'] =
            $usuario->sexo;

        $_SESSION['correo'] =
            $usuario->correo;

        $_SESSION['universidad'] =
            $usuario->universidad;

        $_SESSION['carrera'] =
            $usuario->carrera;

        $_SESSION['admin'] =
            (int) (
                $usuario->admin
                ?? 0
            );
    }

}