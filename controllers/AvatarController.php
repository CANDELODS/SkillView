<?php

namespace Controllers;

class AvatarController
{
    public static function createSession()
    {
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

        $input = self::getRequestData();

        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $context  = isset($input['context']) ? trim((string)$input['context']) : 'lesson';
        $avatarId = isset($input['avatarId']) ? trim((string)$input['avatarId']) : '';
        $voiceId  = isset($input['voiceId']) ? trim((string)$input['voiceId']) : '';

        if ($provider !== 'heygen') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_PROVIDER',
                    'message' => 'El proveedor solicitado no está soportado actualmente.'
                ]
            ], 422);
        }

        $apiKey = self::env('HEYGEN_API_KEY', '');
        $defaultAvatarId = self::env('HEYGEN_AVATAR_ID', '');
        $defaultVoiceId = self::env('HEYGEN_VOICE_ID', '');

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

        if ($avatarId === '') {
            $avatarId = $defaultAvatarId;
        }

        if ($voiceId === '') {
            $voiceId = $defaultVoiceId;
        }

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
            $newSessionPayload = [
                'version' => 'v2',
                'avatar_id' => $avatarId
            ];

            if ($voiceId !== '') {
                $newSessionPayload['voice'] = [
                    'voice_id' => $voiceId
                ];
            }

            $newSessionResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.new',
                $newSessionPayload,
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            $newSessionBody = $newSessionResponse['body'] ?? [];
            $sessionData = $newSessionBody['data'] ?? $newSessionBody;

            $sessionId = $sessionData['session_id'] ?? null;
            $url = $sessionData['url'] ?? null;
            $accessToken = $sessionData['access_token'] ?? null;
            $realAvatarId = $sessionData['avatar_id'] ?? $avatarId;

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

            $startSessionBody = $startSessionResponse['body'] ?? [];

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

    public static function sendTask()
    {
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

        $input = self::getRequestData();

        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $sessionId = isset($input['sessionId']) ? trim((string)$input['sessionId']) : '';
        $text = isset($input['text']) ? trim((string)$input['text']) : '';
        $taskType = isset($input['taskType']) ? trim((string)$input['taskType']) : 'repeat';

        if ($provider !== 'heygen') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_PROVIDER',
                    'message' => 'El proveedor solicitado no está soportado actualmente.'
                ]
            ], 422);
        }

        if ($sessionId === '') {
            $sessionId = $_SESSION['avatar_flow']['sessionId'] ?? '';
        }

        if ($sessionId === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SESSION_ID_REQUIRED',
                    'message' => 'No se recibió el sessionId del avatar.'
                ]
            ], 422);
        }

        if ($text === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'TEXT_REQUIRED',
                    'message' => 'No se recibió texto para narrar.'
                ]
            ], 422);
        }

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

            $taskBody = $taskResponse['body'] ?? [];
            $taskData = $taskBody['data'] ?? $taskBody;

            $taskId = $taskData['task_id'] ?? null;

            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'sessionId' => $sessionId,
                'taskId' => $taskId,
                'remoteTask' => $taskBody
            ], 200);
        } catch (\Throwable $e) {
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

    public static function closeSession()
    {
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

        $input = self::getRequestData();

        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $sessionId = isset($input['sessionId']) ? trim((string)$input['sessionId']) : '';

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

        if ($sessionId === '') {
            $sessionId = $_SESSION['avatar_flow']['sessionId'] ?? '';
        }

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

            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'sessionClosed' => true,
                'sessionId' => $sessionId,
                'remoteStop' => $stopResponse['body'] ?? null
            ], 200);
        } catch (\Throwable $e) {
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

    private static function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function clearAvatarFlow(): void
    {
        unset($_SESSION['avatar_flow']);
    }

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

    private static function requestHeyGen(
        string $method,
        string $url,
        array $payload = [],
        array $headers = []
    ): array {
        $ch = curl_init();

        if ($ch === false) {
            throw new \Exception('No se pudo inicializar cURL.');
        }

        $method = strtoupper(trim($method));
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error cURL HeyGen: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = ['raw' => $response];
        }

        if ($statusCode >= 400) {
            $message =
                $decoded['error']['message'] ??
                $decoded['message'] ??
                'HeyGen respondió con error HTTP ' . $statusCode;

            throw new \Exception($message);
        }

        return [
            'statusCode' => $statusCode,
            'body' => $decoded
        ];
    }
}
