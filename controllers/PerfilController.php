<?php

namespace Controllers;

use Model\Logros;
use Model\Usuario;
use Model\usuarios_habilidades;
use Model\usuarios_logros;
use Model\usuarios_retos;
use MVC\Router;

class PerfilController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;
        $idUsuario = (int)($_SESSION['id'] ?? 0);

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);

        // 1) Datos del usuario (para la tarjeta del perfil)
        $usuario = Usuario::find($idUsuario);

        // Control de seguridad: si por alguna razón el usuario no existe, redirigimos
        if (!$usuario) {
            header('Location: /principal');
            exit;
        }

        // =========================
        // EDICIÓN DE PERFIL (MODAL)
        // =========================
        // Mantenemos el modal abierto si hay errores en el POST
        $mostrarModalEditar = false;

        // Usuario "del formulario" (para no afectar visualmente la tarjeta si hay errores)
        // Clonamos para que el perfil se renderice con datos reales de BD aunque el POST venga vacío
        $usuarioForm = clone $usuario;

        // Alertas para mostrar feedback (usamos el sistema de alertas del modelo)
        $alertas = [];

        // Procesamos edición del perfil (POST a /perfil)
        // Procesamos edición del perfil (POST a /perfil)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Si hubo submit, por defecto mostramos el modal
            // (si todo sale bien, redirigimos y el modal se cerrará)
            $mostrarModalEditar = true;

            // Solo campos permitidos para editar (no tocar correo/password/edad/sexo/admin)
            $data = [
                'nombres'     => $_POST['nombres'] ?? '',
                'apellidos'   => $_POST['apellidos'] ?? '',
                'universidad' => $_POST['universidad'] ?? '',
                'carrera'     => $_POST['carrera'] ?? '',
            ];

            // Actualizamos el objeto sin crear uno nuevo
            // (mantiene id, correo, password, etc.)
            $usuarioForm->sincronizar($data);

            // Validación dedicada para edición de perfil
            // (debe existir en Usuario.php)
            // -> validar_edicion_perfil()
            $alertas = $usuarioForm->validar_edicion_perfil();

            // Si no hay alertas de error, guardamos
            if (empty($alertas)) {
                $resultado = $usuarioForm->guardar();

                if ($resultado) {

                    // Guardamos alerta de éxito
                    Usuario::setAlerta('exito', 'Tu perfil se actualizó correctamente.');

                    // Aplicamos PRG (Post/Redirect/Get)
                    // Evita reenvío del formulario al refrescar
                    // y permite cerrar el modal automáticamente
                    header('Location: ' . $_ENV['HOST'] . '/perfil?actualizado=1');
                    exit;
                } else {
                    Usuario::setAlerta('error', 'No se pudo actualizar el perfil. Inténtalo de nuevo.');
                }
            }

            // Si hay errores:
            // - No redirigimos
            // - El modal permanece abierto
            // - Se muestran las alertas dentro del modal
        }

        // Unificamos alertas del modelo
        $alertas = Usuario::getAlertas();

        // 2) Progreso general (promedio del progreso en usuarios_habilidades)
        $progresoGeneral = usuarios_habilidades::progresoGeneral($idUsuario);

        // 3) Puntos totales (sumatoria de puntaje_obtenido en retos completados)
        $puntosTotales = usuarios_retos::puntosTotalesUsuario($idUsuario);

        // 4) Progreso por habilidad (tabla)
        $progresoPorHabilidad = usuarios_habilidades::progresoPorHabilidad($idUsuario);

        // 5) Logros / medallas (destacados)
        $medallas = Logros::destacados(6);

        // Lookup de logros del usuario: [id_logro => fecha]
        $logrosUsuarioLookup = usuarios_logros::lookupLogrosUsuario($idUsuario);

        foreach ($medallas as $medalla) {
            $idLogro = (int)$medalla->id;
            $medalla->desbloqueado = isset($logrosUsuarioLookup[$idLogro]);
            $medalla->fecha_obtenido = $logrosUsuarioLookup[$idLogro] ?? null;
        }

        // Render a la vista 
        $router->render('paginas/perfil/perfil', [
            'titulo' => 'Perfil',
            'login'  => $login,

            // Header
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],

            // Perfil
            'usuario' => $usuario,
            'usuarioForm' => $usuarioForm,
            'progresoGeneral' => $progresoGeneral,
            'puntosTotales' => $puntosTotales,
            'progresoPorHabilidad' => $progresoPorHabilidad,
            'medallas' => $medallas,

            // Modal editar perfil
            'alertas' => $alertas,
            'mostrarModalEditar' => $mostrarModalEditar
        ]);
    }
}
