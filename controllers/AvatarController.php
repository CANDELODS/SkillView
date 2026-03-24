<?php

namespace Controllers;

// Controlador encargado de toda la comunicación entre SkillView y HeyGen
// para el manejo del avatar en tiempo real.
class AvatarController
{
    // -----------------------------------------------------------------
    // Crea una nueva sesión de avatar
    // -----------------------------------------------------------------
    public static function createSession()
    {
        // Validamos que el usuario tenga sesión iniciada.
        // Si no está autenticado, devolvemos error 401 y una redirección.
        if (!isAuth()) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Tu sesión ha expirado.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Obtenemos el id del usuario autenticado desde la sesión.
        // Esto sirve como validación extra y también para guardar trazabilidad.
        $idUsuario = (int)($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No se pudo identificar al usuario.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Leemos el cuerpo de la petición (JSON o POST tradicional).
        $input = self::getRequestData();

        // Extraemos los parámetros enviados desde el frontend.
        // Si no vienen, usamos valores por defecto.
        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $context  = isset($input['context']) ? trim((string)$input['context']) : 'lesson';
        $avatarId = isset($input['avatarId']) ? trim((string)$input['avatarId']) : '';
        $voiceId  = isset($input['voiceId']) ? trim((string)$input['voiceId']) : '';

        // Actualmente el sistema solo soporta HeyGen como proveedor.
        // Si llega otro valor, rechazamos la petición.
        if ($provider !== 'heygen') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_PROVIDER',
                    'message' => 'El proveedor solicitado no está soportado actualmente.'
                ]
            ], 422);
        }

        // Leemos desde variables de entorno la API key de HeyGen
        // y los valores por defecto del avatar y la voz.
        $apiKey = self::env('HEYGEN_API_KEY', '');
        $defaultAvatarId = self::env('HEYGEN_AVATAR_ID', '');
        $defaultVoiceId = self::env('HEYGEN_VOICE_ID', '');

        // Si no existe API key configurada, no se puede hablar con HeyGen.
        if ($apiKey === '') {
            error_log('AvatarController.createSession: falta HEYGEN_API_KEY');

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_CONFIG_MISSING',
                    'message' => 'La configuración del avatar no está disponible.'
                ]
            ], 500);
        }

        // Si el frontend no mandó avatarId, usamos el configurado en .env.
        if ($avatarId === '') {
            $avatarId = $defaultAvatarId;
        }

        // Si el frontend no mandó voiceId, usamos el configurado en .env.
        if ($voiceId === '') {
            $voiceId = $defaultVoiceId;
        }

        // El avatar_id es obligatorio para poder crear la sesión.
        if ($avatarId === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_ID_REQUIRED',
                    'message' => 'No se configuró el avatar_id de HeyGen.'
                ]
            ], 422);
        }

        try {
            // Armamos el payload inicial para crear la sesión de streaming.
            // version v2 corresponde al flujo de Streaming Avatar de HeyGen.
            $newSessionPayload = [
                'version' => 'v2',
                'avatar_id' => $avatarId
            ];

            // La voz es opcional.
            // Solo la agregamos si existe.
            if ($voiceId !== '') {
                $newSessionPayload['voice'] = [
                    'voice_id' => $voiceId
                ];
            }

            // Llamada a HeyGen para crear la sesión de streaming.
            // Esto devuelve datos como session_id, url y access_token.
            $newSessionResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.new',
                $newSessionPayload,
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            // Tomamos el body completo de la respuesta.
            $newSessionBody = $newSessionResponse['body'] ?? [];

            // Algunas respuestas vienen anidadas en "data", otras no.
            // Por eso primero intentamos leer body['data'] y si no existe, body completo.
            $sessionData = $newSessionBody['data'] ?? $newSessionBody;

            // Extraemos los datos importantes que el frontend necesita para conectar LiveKit.
            $sessionId = $sessionData['session_id'] ?? null;
            $url = $sessionData['url'] ?? null;
            $accessToken = $sessionData['access_token'] ?? null;
            $realAvatarId = $sessionData['avatar_id'] ?? $avatarId;

            // Validamos que HeyGen haya respondido con toda la información necesaria.
            if (!$sessionId || !$url || !$accessToken) {
                error_log(
                    'AvatarController.createSession: respuesta inesperada de streaming.new -> ' .
                        json_encode($newSessionBody, JSON_UNESCAPED_UNICODE)
                );

                self::jsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'AVATAR_SESSION_INVALID',
                        'message' => 'HeyGen no devolvió los datos completos de la sesión.'
                    ]
                ], 502);
            }

            // Una vez creada la sesión, la iniciamos.
            // Este paso deja el avatar listo para empezar a recibir tareas de voz.
            $startSessionResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.start',
                [
                    'session_id' => $sessionId
                ],
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            // Guardamos la respuesta del start por si el frontend la quiere inspeccionar.
            $startSessionBody = $startSessionResponse['body'] ?? [];

            // Guardamos una pequeña traza de la sesión en $_SESSION.
            // Esto permite reutilizar algunos datos después, por ejemplo al enviar tareas
            // o al cerrar la sesión del avatar.
            $_SESSION['avatar_flow'] = [
                'provider' => 'heygen',
                'context' => $context,
                'sessionId' => $sessionId,
                'avatarId' => $realAvatarId,
                'voiceId' => $voiceId,
                'url' => $url,
                'accessToken' => $accessToken,
                'createdAt' => date('c'),
                'createdBy' => $idUsuario
            ];

            // Respondemos al frontend con todo lo necesario para montar el avatar.
            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'context' => $context,
                'sessionId' => $sessionId,
                'url' => $url,
                'accessToken' => $accessToken,
                'avatarId' => $realAvatarId,
                'voiceId' => $voiceId !== '' ? $voiceId : null,
                'remoteStart' => $startSessionBody
            ], 200);
        } catch (\Throwable $e) {
            // Si cualquier paso falla, lo registramos en logs,
            // limpiamos la sesión local del avatar y devolvemos error al frontend.
            error_log('AvatarController.createSession error: ' . $e->getMessage());

            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_SESSION_ERROR',
                    'message' => 'No se pudo crear la sesión del avatar.',
                    'debug' => $e->getMessage()
                ]
            ], 502);
        }
    }

    // -----------------------------------------------------------------
    // Envía una tarea de voz al avatar
    // -----------------------------------------------------------------
    public static function sendTask()
    {
        // Validamos sesión del usuario.
        if (!isAuth()) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Tu sesión ha expirado.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Validamos que exista un id de usuario en sesión.
        $idUsuario = (int)($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No se pudo identificar al usuario.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Leemos los datos enviados desde el frontend.
        $input = self::getRequestData();

        // Parámetros esperados para mandar una tarea al avatar.
        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $sessionId = isset($input['sessionId']) ? trim((string)$input['sessionId']) : '';
        $text = isset($input['text']) ? trim((string)$input['text']) : '';
        $taskType = isset($input['taskType']) ? trim((string)$input['taskType']) : 'repeat';

        // Solo soportamos HeyGen.
        if ($provider !== 'heygen') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_PROVIDER',
                    'message' => 'El proveedor solicitado no está soportado actualmente.'
                ]
            ], 422);
        }

        // Si el frontend no manda sessionId, intentamos recuperarlo
        // desde la sesión local guardada al crear el avatar.
        if ($sessionId === '') {
            $sessionId = $_SESSION['avatar_flow']['sessionId'] ?? '';
        }

        // El sessionId es obligatorio para que HeyGen sepa a qué sesión enviar la tarea.
        if ($sessionId === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SESSION_ID_REQUIRED',
                    'message' => 'No se recibió el sessionId del avatar.'
                ]
            ], 422);
        }

        // El texto a narrar también es obligatorio.
        if ($text === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'TEXT_REQUIRED',
                    'message' => 'No se recibió texto para narrar.'
                ]
            ], 422);
        }

        // Leemos la API key desde variables de entorno.
        $apiKey = self::env('HEYGEN_API_KEY', '');
        if ($apiKey === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_CONFIG_MISSING',
                    'message' => 'La configuración del avatar no está disponible.'
                ]
            ], 500);
        }

        try {
            // Enviamos la tarea a HeyGen.
            // task_type "repeat" indica que el avatar debe repetir/narrar el texto recibido.
            $taskResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.task',
                [
                    'session_id' => $sessionId,
                    'text' => $text,
                    'task_type' => $taskType
                ],
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            // Leemos la respuesta.
            $taskBody = $taskResponse['body'] ?? [];
            $taskData = $taskBody['data'] ?? $taskBody;

            // Intentamos obtener el id de la tarea creada.
            $taskId = $taskData['task_id'] ?? null;

            // Devolvemos confirmación al frontend.
            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'sessionId' => $sessionId,
                'taskId' => $taskId,
                'remoteTask' => $taskBody
            ], 200);
        } catch (\Throwable $e) {
            // Si falla el envío de la tarea, devolvemos error.
            error_log('AvatarController.sendTask error: ' . $e->getMessage());

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_TASK_ERROR',
                    'message' => 'No se pudo enviar la tarea al avatar.',
                    'debug' => $e->getMessage()
                ]
            ], 502);
        }
    }

    // -----------------------------------------------------------------
    // Cierra una sesión de avatar activa
    // -----------------------------------------------------------------
    public static function closeSession()
    {
        // Validación de autenticación.
        if (!isAuth()) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Tu sesión ha expirado.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Validación extra del usuario en sesión.
        $idUsuario = (int)($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No se pudo identificar al usuario.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Leemos los datos enviados.
        $input = self::getRequestData();

        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $sessionId = isset($input['sessionId']) ? trim((string)$input['sessionId']) : '';

        // Si el proveedor no es HeyGen, no hay nada que cerrar remotamente.
        if ($provider !== 'heygen') {
            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => $provider,
                'sessionClosed' => false,
                'message' => 'No había una sesión compatible para cerrar.'
            ], 200);
        }

        // Si el frontend no manda sessionId, intentamos sacarlo de la sesión local.
        if ($sessionId === '') {
            $sessionId = $_SESSION['avatar_flow']['sessionId'] ?? '';
        }

        // Si sigue vacío, limpiamos estado local y respondemos sin error fatal.
        if ($sessionId === '') {
            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'sessionClosed' => false,
                'message' => 'No se recibió sessionId; solo se limpió el estado local.'
            ], 200);
        }

        // Leemos la API key.
        $apiKey = self::env('HEYGEN_API_KEY', '');
        if ($apiKey === '') {
            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_CONFIG_MISSING',
                    'message' => 'La configuración del avatar no está disponible.'
                ]
            ], 500);
        }

        try {
            // Llamamos a HeyGen para detener/cerrar la sesión remota.
            $stopResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.stop',
                [
                    'session_id' => $sessionId
                ],
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            // Limpiamos la información local del avatar en la sesión PHP.
            self::clearAvatarFlow();

            // Respondemos confirmando el cierre.
            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'sessionClosed' => true,
                'sessionId' => $sessionId,
                'remoteStop' => $stopResponse['body'] ?? null
            ], 200);
        } catch (\Throwable $e) {
            // Si falla el cierre remoto, también limpiamos el estado local
            // y devolvemos el error al frontend.
            error_log('AvatarController.closeSession error: ' . $e->getMessage());

            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_CLOSE_ERROR',
                    'message' => 'No se pudo cerrar la sesión del avatar.',
                    'debug' => $e->getMessage()
                ]
            ], 502);
        }
    }

    // -----------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------

    // Lee el cuerpo de la petición.
    // Primero intenta decodificar JSON desde php://input.
    // Si no hay JSON válido, usa $_POST como respaldo.
    private static function getRequestData(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST ?? [];
    }

    // Envía una respuesta JSON uniforme y finaliza la ejecución.
    private static function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Limpia el estado local del avatar guardado en la sesión.
    private static function clearAvatarFlow(): void
    {
        unset($_SESSION['avatar_flow']);
    }

    // Helper para leer variables de entorno.
    // Primero intenta $_ENV, luego getenv(), y si no existe devuelve el default.
    private static function env(string $key, $default = '')
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }

    // Helper genérico para hacer peticiones HTTP a HeyGen usando cURL.
    // Recibe método, URL, payload y headers.
    private static function requestHeyGen(
        string $method,
        string $url,
        array $payload = [],
        array $headers = []
    ): array {
        // Inicializamos cURL.
        $ch = curl_init();

        if ($ch === false) {
            throw new \Exception('No se pudo inicializar cURL.');
        }

        // Normalizamos método HTTP y convertimos el payload a JSON.
        $method = strtoupper(trim($method));
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Configuración principal de la petición.
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        // Ejecutamos la petición.
        $response = curl_exec($ch);

        // Si cURL falla a nivel de red/transporte, lanzamos excepción.
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error cURL HeyGen: ' . $error);
        }

        // Obtenemos el código HTTP y cerramos el recurso cURL.
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Intentamos decodificar la respuesta JSON.
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = ['raw' => $response];
        }

        // Si HeyGen responde con error HTTP, construimos un mensaje legible
        // y lanzamos excepción para que el método llamador lo maneje.
        if ($statusCode >= 400) {
            $message =
                $decoded['error']['message'] ??
                $decoded['message'] ??
                'HeyGen respondió con error HTTP ' . $statusCode;

            throw new \Exception($message);
        }

        // Si todo salió bien, devolvemos código HTTP y body ya decodificado.
        return [
            'statusCode' => $statusCode,
            'body' => $decoded
        ];
    }
}
