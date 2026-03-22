<?php

namespace Controllers;

class AvatarController
{
    public static function createSession()
    {
        // 1) Validar autenticación
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

        // 2) Leer payload
        $input = self::getRequestData();

        $provider = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
        $context  = isset($input['context']) ? trim((string)$input['context']) : 'lesson';
        $avatarId = isset($input['avatarId']) ? trim((string)$input['avatarId']) : '';
        $voiceId  = isset($input['voiceId']) ? trim((string)$input['voiceId']) : '';

        if ($provider === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'El proveedor del avatar es obligatorio.'
                ]
            ], 422);
        }

        if ($provider !== 'heygen') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_PROVIDER',
                    'message' => 'El proveedor solicitado no está soportado actualmente.'
                ]
            ], 422);
        }

        // 3) Obtener configuración
        $apiKey = self::env('HEYGEN_API_KEY', '');
        $defaultAvatarId = self::env('HEYGEN_AVATAR_ID', '');
        $defaultVoiceId  = self::env('HEYGEN_VOICE_ID', '');

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

        // 4) Pedir token efímero a HeyGen
        try {
            $heygenResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.create_token',
                [],
                [
                    'x-api-key: ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            $body = $heygenResponse['body'] ?? [];
            $token =
                $body['data']['token'] ??
                $body['token'] ??
                $body['data']['access_token'] ??
                $body['access_token'] ??
                null;

            if (!$token) {
                error_log('AvatarController.createSession: respuesta inesperada de HeyGen -> ' . json_encode($body));
                self::jsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'AVATAR_TOKEN_NOT_RETURNED',
                        'message' => 'No se pudo obtener el token de sesión del avatar.'
                    ]
                ], 502);
            }

            // Guardamos un pequeño estado local opcional para trazabilidad
            $_SESSION['avatar_flow'] = [
                'provider' => 'heygen',
                'context' => $context,
                'avatarId' => $avatarId,
                'voiceId' => $voiceId,
                'createdAt' => date('c'),
                'createdBy' => $idUsuario
            ];

            self::jsonResponse([
                'ok' => true,
                'error' => null,
                'provider' => 'heygen',
                'token' => $token,
                'sessionId' => null,
                'avatarId' => $avatarId ?: null,
                'voiceId' => $voiceId ?: null,
                'context' => $context
            ], 200);
        } catch (\Throwable $e) {
            error_log('AvatarController.createSession error: ' . $e->getMessage());

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_SESSION_ERROR',
                    'message' => 'No se pudo crear la sesión del avatar.'
                ]
            ], 502);
        }
    }

    public static function closeSession()
    {
        // 1) Validar autenticación
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

        // 2) Leer payload
        $input = self::getRequestData();

        $provider  = isset($input['provider']) ? trim((string)$input['provider']) : 'heygen';
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

        // Si todavía no tienes sessionId real desde frontend / SDK,
        // no fallamos: simplemente limpiamos estado local y devolvemos ok.
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

        // 3) Cerrar sesión remota en HeyGen
        try {
            $heygenResponse = self::requestHeyGen(
                'POST',
                'https://api.heygen.com/v1/streaming.stop',
                [
                    'session_id' => $sessionId
                ],
                [
                    'x-api-key: ' . $apiKey,
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
                'remoteResponse' => $heygenResponse['body'] ?? null
            ], 200);
        } catch (\Throwable $e) {
            error_log('AvatarController.closeSession error: ' . $e->getMessage());

            self::clearAvatarFlow();

            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'AVATAR_CLOSE_ERROR',
                    'message' => 'No se pudo cerrar la sesión del avatar.'
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
        $jsonPayload = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : '{}';

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

        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = [
                'raw' => $response
            ];
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