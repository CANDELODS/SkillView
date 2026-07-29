<?php

declare(strict_types=1);

namespace Classes;

/**
 * Centraliza las reglas puras utilizadas por las
 * funciones auxiliares globales de SkillView.
 */
final class FuncionesAuxiliaresService
{
    /**
     * Información utilizada cuando no se puede
     * identificar al usuario del encabezado.
     */
    private const DATOS_HEADER_POR_DEFECTO = [
        'nombreUsuario' => 'Juan Candelo',
        'inicialesUsuario' => 'JC'
    ];

    /**
     * Convierte caracteres especiales en entidades HTML.
     *
     * Esto permite mostrar contenido ingresado por el usuario
     * sin interpretarlo como etiquetas o código ejecutable.
     */
    public static function sanitizarHtml(
        mixed $valor
    ): string {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Comprueba solamente la estructura básica
     * de las credenciales almacenadas en sesión.
     *
     * La consulta del usuario en MySQL permanece
     * dentro de la función global isAuth().
     */
    public static function sesionTieneCredencialesValidas(
        array $sesion
    ): bool {
        $correo = trim(
            (string)($sesion['correo'] ?? '')
        );

        if ($correo === '') {
            return false;
        }

        $usuarioId = filter_var(
            $sesion['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        return $usuarioId !== false
            && $usuarioId > 0;
    }

    /**
     * Determina si una ruta corresponde exactamente
     * a la página actual o a una de sus subrutas.
     */
    public static function esPaginaActual(
        ?string $pathInfo,
        string $ruta
    ): bool {
        $pathInfo = trim(
            (string) $pathInfo
        );

        $ruta = trim($ruta);

        if (
            $pathInfo === ''
            || $ruta === ''
        ) {
            return false;
        }

        /*
         * La raíz solamente coincide con la raíz.
         * De esta forma "/" no marca como activas
         * todas las rutas del sistema.
         */
        if ($ruta === '/') {
            return $pathInfo === '/';
        }

        // Normaliza las barras externas.
        $ruta = '/' . trim($ruta, '/');
        $pathInfo = '/' . trim($pathInfo, '/');

        return $pathInfo === $ruta
            || str_starts_with(
                $pathInfo,
                $ruta . '/'
            );
    }

    /**
     * Construye el nombre corto y las iniciales
     * mostradas en el encabezado.
     */
    public static function construirDatosHeader(
        ?string $nombres,
        ?string $apellidos
    ): array {
        $primerNombre =
            self::primeraPalabra($nombres);

        $primerApellido =
            self::primeraPalabra($apellidos);

        if (
            $primerNombre === ''
            && $primerApellido === ''
        ) {
            return self::DATOS_HEADER_POR_DEFECTO;
        }

        $nombreUsuario = trim(
            $primerNombre
            . ' '
            . $primerApellido
        );

        $iniciales = mb_strtoupper(
            mb_substr(
                $primerNombre,
                0,
                1,
                'UTF-8'
            )
            . mb_substr(
                $primerApellido,
                0,
                1,
                'UTF-8'
            ),
            'UTF-8'
        );

        return [
            'nombreUsuario' => $nombreUsuario,
            'inicialesUsuario' => $iniciales
        ];
    }

    /**
     * Obtiene la primera palabra de un campo,
     * ignorando espacios, tabulaciones y saltos.
     */
    private static function primeraPalabra(
        ?string $texto
    ): string {
        $texto = trim(
            (string) $texto
        );

        if ($texto === '') {
            return '';
        }

        $partes = preg_split(
            '/\s+/u',
            $texto,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return is_array($partes)
            ? (string)($partes[0] ?? '')
            : '';
    }
}