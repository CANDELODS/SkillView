<?php

namespace Controllers;

use Classes\Paginacion;
use Model\HabilidadesBlandas;
use Model\Usuario;
use MVC\Router;

class DashboardController
{
    public static function index(Router $router)
    {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

        // Render a la vista 
        $router->render('admin/dashboard/index', [
            'titulo' => 'Panel de administración'
        ]);
    }
//----------------------------------ADMINISTRAR USUARIOS----------------------------------
    public static function indexUsuarios(Router $router)
    {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

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

    public static function editarUsuarios(
        Router $router
    ): void {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

        $alertas = [];
        $alertasExito = [];

        // -------------------------
        // VALIDACIÓN DEL USUARIO
        // -------------------------
        $id =
            filter_var(
                $_GET['id']
                    ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1
                    ]
                ]
            );

        if ($id === false) {
            header(
                'Location: /admin/usuarios'
            );
            exit;
        }

        $usuario =
            Usuario::find(
                (int) $id
            );

        if (!$usuario) {
            header(
                'Location: /admin/usuarios'
            );
            exit;
        }

        if (
            $_SERVER['REQUEST_METHOD']
            === 'POST'
        ) {
            /*
             * Guardamos los valores internos que no deben alterarse
             * directamente desde el formulario administrativo.
             */
            $passwordOriginal =
                (string) $usuario->password;

            $debeCambiarPasswordOriginal =
                (int) (
                    $usuario->
                        debe_cambiar_password
                    ?? 0
                );

            /*
             * Solo sincronizamos los campos que realmente pertenecen
             * al formulario de edición.
             *
             * Cualquier dato adicional enviado manualmente por POST,
             * por ejemplo:
             * - admin;
             * - debe_cambiar_password;
             * - autoriza_tratamiento_datos;
             * - token_recuperacion;
             * - token_expiracion;
             *
             * queda ignorado y conserva el valor almacenado.
             */
            $datosPermitidos = [
                'nombres' =>
                    $_POST['nombres']
                    ?? '',
                'apellidos' =>
                    $_POST['apellidos']
                    ?? '',
                'edad' =>
                    $_POST['edad']
                    ?? '',
                'sexo' =>
                    $_POST['sexo']
                    ?? '',
                'correo' =>
                    $_POST['correo']
                    ?? '',
                'universidad' =>
                    $_POST['universidad']
                    ?? '',
                'carrera' =>
                    $_POST['carrera']
                    ?? '',
                'password' =>
                    $_POST['password']
                    ?? '',
                'password2' =>
                    $_POST['password2']
                    ?? '',
                /*
                 * Si por alguna razón el formulario no envía el
                 * estado, se conserva el valor actual.
                 */
                'habilitado' =>
                    $_POST['habilitado']
                    ?? $usuario->habilitado
            ];

            $usuario->sincronizar(
                $datosPermitidos
            );

            // -------------------------
            // VALIDACIONES DEL MODELO
            // -------------------------
            $alertas =
                $usuario->validar_edicion();

            /*
             * El mismo correo del usuario es válido.
             * Solo se rechaza si pertenece a una cuenta diferente.
             */
            if (
                empty($alertas)
                && Usuario::
                    correoEnUsoPorOtroUsuario(
                        (int) $usuario->id,
                        (string) $usuario->correo
                    )
            ) {
                $alertas['error'][] =
                    'El correo ya está registrado por otro usuario';
            }

            /*
             * El administrador que mantiene la sesión activa no puede
             * deshabilitar su propia cuenta. Esto evita perder el
             * acceso administrativo durante la misma operación.
             */
            $idAdministradorActual =
                (int) (
                    $_SESSION['id']
                    ?? 0
                );

            if (
                empty($alertas)
                && (int) $usuario->id
                    === $idAdministradorActual
                && (int) $usuario->habilitado === 0
            ) {
                $alertas['error'][] =
                    'No puedes deshabilitar tu propia cuenta mientras tienes una sesión administrativa activa';
            }

            if (empty($alertas)) {
                // -------------------------
                // CONTRASEÑA OPCIONAL
                // -------------------------
                if (
                    (string) $usuario->password
                    !== ''
                ) {
                    /*
                     * Una contraseña escrita por el administrador se
                     * considera temporal:
                     *
                     * - se almacena únicamente su hash;
                     * - la contraseña anterior deja de ser válida;
                     * - se exige al usuario cambiarla en el próximo
                     *   inicio de sesión.
                     */
                    $usuario->hashPassword();

                    $usuario->
                        debe_cambiar_password = 1;
                } else {
                    /*
                     * Si no se escribió una nueva contraseña,
                     * se conserva exactamente el hash anterior y
                     * también el estado previo de cambio obligatorio.
                     */
                    $usuario->password =
                        $passwordOriginal;

                    $usuario->
                        debe_cambiar_password =
                        $debeCambiarPasswordOriginal;
                }

                /*
                 * password2 no forma parte de la tabla usuarios.
                 * Se deja vacío después de la validación para evitar
                 * conservar información innecesaria en el objeto.
                 */
                $usuario->password2 = '';

                // ActiveRecord actualiza únicamente la fila del usuario.
                // Las tablas de progreso no son eliminadas ni modificadas.
                $resultado =
                    $usuario->guardar();

                if ($resultado) {
                    $alertasExito[] =
                        'El usuario se actualizó correctamente';
                } else {
                    $alertas['error'][] =
                        'Ocurrió un error al guardar el usuario';
                }
            }
        }

        // Render a la vista.
        $router->render(
            'admin/usuarios/editar',
            [
                'titulo' =>
                    'Editar Usuario',
                'alertas' =>
                    $alertas,
                'alertasExito' =>
                    $alertasExito,
                'usuario' =>
                    $usuario
            ]
        );
    }

    public static function eliminarUsuarios()
    {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

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
//----------------------------------FIN ADMINISTRAR USUARIOS----------------------------------

//----------------------------------ADMINISTRAR HABILIDADES----------------------------------
    public static function indexHabilidades(Router $router)
    {

        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

        // Obtenemos la búsqueda desde la URL
        $busqueda = $_GET['busqueda'] ?? '';

        // Obtenemos la página desde la URL y verificamos que sea un número y que no sea negativo
        $pagina_actual = $_GET['page'] ?? 1;
        $pagina_actual = filter_var($pagina_actual, FILTER_VALIDATE_INT);

        //Evitamos: URLs mal formadas, Números negativos, Inyecciones tipo page=asdf, Que un usuario manipule la paginación, Que la app rompa al calcular offset
        if (!$pagina_actual || $pagina_actual < 1) {
            // Si hay búsqueda, la mantenemos en la redirección
            //Ya que si el usuario está buscando algo, lo redirigimos sin perder la busqueda
            $url = '/admin/habilidades?page=1';
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
        $habilidades = [];

        // Si hay búsqueda, usamos métodos especiales con WHERE + LIKE
        if ($busqueda !== '') {

            // Total de registros que cumplen la búsqueda
            $total_registros = HabilidadesBlandas::totalBusquedaHabilidades($busqueda);

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
                $url = '/admin/habilidades?page=1&busqueda=' . urlencode($busqueda);
                header("Location: {$url}");
                exit;
            }

            // Traemos las habilidades filtradas y paginadas
            $habilidades = HabilidadesBlandas::paginarBusquedaHabilidades(
                $busqueda,
                $registros_por_pagina,
                $paginacion->offset()
            );
        } else {
            // Listado normal sin búsqueda

            $total_registros = HabilidadesBlandas::total();

            $paginacion = new Paginacion(
                $pagina_actual,
                $registros_por_pagina,
                $total_registros
            );

            // Redireccionamos si la página actual es mayor al total de páginas
            if ($paginacion->totalPaginas() > 0 && $pagina_actual > $paginacion->totalPaginas()) {
                header('Location: /admin/habilidades?page=1');
                exit;
            }

            // Traemos las habilidades paginadas normalmente
            $habilidades = HabilidadesBlandas::paginar('nombre', $registros_por_pagina, $paginacion->offset());
        }

        // Render a la vista 
        $router->render('admin/habilidades/index', [
            'titulo'     => 'Gestión de Habilidades',
            'habilidades'   => $habilidades,
            'paginacion' => $paginacion->paginacion(),
            'busqueda'   => $busqueda
        ]);
    }

    public static function crearHabilidades(Router $router)
    {
        $alertas = [];
        $alertasExito = [];
        $habilidad = new HabilidadesBlandas;
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $habilidad->sincronizar($_POST);
            //Validar
            $alertas = $habilidad->validar();
            //Si no hay alertas, guardamos
            if(empty($alertas)){
                $resultado = $habilidad->guardar();
                if ($resultado) {
                    $alertasExito[] = "La habilidad se creó correctamente";
                } else {
                    $alertas['error'][] = "Ocurrió un error al crear la habilidad";
                }
            }
        }
        // Render a la vista 
        $router->render('admin/habilidades/crear', [
            'titulo' => 'Crear Habilidad',
            'alertas' => $alertas,
            'alertasExito' => $alertasExito,
            'habilidad' => $habilidad
        ]);
    }

    public static function editarHabilidades(Router $router)
    {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();
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
        //Obtenemos la habilidad a editar
        $habilidad = HabilidadesBlandas::find($id);
        //Validamos si la habilidad existe
        if (!$habilidad) {
            header('Location: /admin/habilidades');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            //Sincronizamos con los datos del formulario
            $habilidad->sincronizar($_POST);
            //Validamos
            $alertas = $habilidad->validar();
            //Si no hay alertar, guardamos
            if (empty($alertas)) {
                //Actualizamos la habilidad
                $resultado = $habilidad->guardar();

                if ($resultado) {
                    $alertasExito[] = "La habilidad de actualizó correctamente";
                } else {
                    $alertas['error'][] = "Ocurrió un error al actualizarla habilidad";
                }
            }
        }
        // Render a la vista 
        $router->render('admin/habilidades/editar', [
            'titulo' => 'Editar Habilidad',
            'alertas' => $alertas,
            'alertasExito' => $alertasExito,
            'habilidad' => $habilidad
        ]);
    }

    public static function eliminarHabilidades()
    {
        /*
         * Todas las acciones de este controlador pertenecen al
         * panel administrativo. La autorización se valida desde
         * un único método para evitar diferencias entre rutas.
         */
        self::protegerRutaAdministrativa();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $habilidad = HabilidadesBlandas::find($id);
            if (!isset($habilidad)) {
                $_SESSION['alertas']['error'][] = "No se pudo eliminar la habilidad";
                header('Location: /admin/habilidades');
                exit;
            }
            $resultado = $habilidad->eliminar();
            if ($resultado) {
                // Guardamos la alerta en sesión para mostrarla después del redirect
                $_SESSION['alertas']['exito'][] = "La habilidad se eliminó correctamente";
                header('Location: /admin/habilidades');
                exit;
            }
        }
    }
//----------------------------------FIN ADMINISTRAR HABILIDADES----------------------------------

    /**
     * Protege todas las rutas administrativas.
     *
     * Reglas:
     * - Sin sesión autenticada: volver al inicio de sesión.
     * - Usuario autenticado sin rol administrador: volver a /principal.
     * - Administrador autenticado: continuar normalmente.
     *
     * isAdmin() reutiliza la sesión actual y comprueba que admin = 1.
     */
    private static function protegerRutaAdministrativa(): void
    {
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        if (!isAdmin()) {
            header('Location: /principal');
            exit;
        }
    }

}