<?php

namespace Classes;

class OpenAIService
{
    // Guarda la API Key leída desde variables de entorno
    private string $apiKey;

    // URL base del endpoint de OpenAI que vamos a consumir
    private string $baseUrl;

    // Modelo que usará OpenAI para generar la respuesta
    private string $model;

    public function __construct()
    {
        // Leemos la API Key desde .env
        // Si no existe, dejamos string vacío para luego lanzar excepción controlada
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

        // Endpoint de la Responses API
        $this->baseUrl = 'https://api.openai.com/v1/responses';

        // Modelo configurable desde .env, con valor por defecto si no existe
        $this->model = $_ENV['OPENAI_MODEL'] ?? 'gpt-5.4';
    }

    /**
     * Envía un prompt a OpenAI y devuelve únicamente el texto generado.
     *
     * @param string $instructions Reglas o instrucciones del sistema para el modelo
     * @param string $input Contenido dinámico que describe la tarea actual
     * @return string Texto generado por OpenAI
     * @throws \Exception Si falta API Key, falla cURL, falla OpenAI o no se puede leer la respuesta
     */
    public function generateText(string $instructions, string $input): string
    {
        // Validamos que exista la API Key
        if (!$this->apiKey) {
            throw new \Exception('OPENAI_API_KEY no está configurada.');
        }

        // Construimos el payload que se enviará a OpenAI
        $payload = [
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $input
        ];

        // Inicializamos una conexión cURL al endpoint de OpenAI
        $ch = curl_init($this->baseUrl);

        // Configuramos la petición HTTP POST
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, // Devuelve la respuesta como string en lugar de imprimirla
            CURLOPT_POST => true,           // Método POST
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30
        ]);

        // Ejecutamos la petición
        $response = curl_exec($ch);

        // Guardamos el código HTTP de la respuesta (200, 400, 401, 500, etc.)
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Si cURL falla a nivel de red/SSL/conexión, lanzamos excepción
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error CURL OpenAI: ' . $error);
        }

        curl_close($ch);

        // Convertimos la respuesta JSON en array asociativo
        $decoded = json_decode($response, true);

        // Si OpenAI respondió con error HTTP, intentamos extraer el mensaje
        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Error desconocido al consultar OpenAI.';
            throw new \Exception('OpenAI HTTP ' . $httpCode . ': ' . $message);
        }

        // Primer intento de lectura del texto devuelto por la Responses API
        if (!empty($decoded['output'][0]['content'][0]['text'])) {
            return trim($decoded['output'][0]['content'][0]['text']);
        }

        // Fallback por si la respuesta viene en otro shape más simple
        if (!empty($decoded['output_text'])) {
            return trim($decoded['output_text']);
        }

        // Si no pudimos extraer texto, devolvemos error controlado
        throw new \Exception('No se pudo extraer texto de la respuesta de OpenAI.');
    }
}