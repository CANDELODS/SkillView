<?php

namespace Classes;

// Servicio encargado de toda la lógica de IA para los retos.
// Su responsabilidad principal es:
// - construir prompts,
// - enviar instrucciones al servicio OpenAIService,
// - validar la respuesta JSON,
// - devolver datos limpios al controlador.
//
// En otras palabras, esta clase es la capa intermedia entre:
// RetoController <-> OpenAIService <-> OpenAI API
class ChallengeAIService
{
    // Instancia del servicio base que realmente hace la conexión HTTP con OpenAI.
    // ChallengeAIService NO habla directamente con cURL;
    // delega esa responsabilidad en OpenAIService.
    private OpenAIService $openAIService;

    // Lista cerrada de razones válidas por las que una respuesta puede requerir retry.
    // Esto ayuda a normalizar la respuesta de la IA y evita valores inesperados.
    private const ALLOWED_RETRY_REASONS = [
        'TOO_GENERIC',
        'OFF_TOPIC',
        'INCOHERENT',
        'LOW_REFLECTION',
        'NO_ACTIONABLE_IDEA',
        'INSUFFICIENT_DEVELOPMENT',
    ];

    // Lista cerrada de niveles de desempeño permitidos.
    // También sirve para validar y corregir respuestas de la IA.
    private const ALLOWED_PERFORMANCE_LEVELS = [
        'EXCELLENT',
        'GOOD',
        'ACCEPTABLE',
        'INSUFFICIENT',
    ];

    // Constructor del servicio.
    // Aquí se crea la instancia interna de OpenAIService.
    public function __construct()
    {
        $this->openAIService = new OpenAIService();
    }

    /**
     * Genera los mensajes iniciales del reto.
     */
    public function generateInitialMessages(
        string $challengeTitle,
        string $skillName,
        array $challengeContent,
        string $userName = ''
    ): array {
        // "instructions" representa las reglas del sistema que se le envían al modelo.
        // Aquí se le dice explícitamente:
        // - quién es,
        // - qué debe hacer,
        // - cómo debe responder,
        // - y cuál es el formato JSON esperado.
        //
        // Esta parte es crítica porque controla el comportamiento del modelo.
        $instructions = <<<TXT
Eres el asistente virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.

Tu tarea es generar SOLO los mensajes iniciales de un reto.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional y motivador.
- No uses markdown.
- No evalúes todavía al usuario.
- No hagas todavía la consigna principal del reto.
- Solo introduce brevemente:
  1. bienvenida
  2. habilidad que se trabajará
  3. objetivo general del reto
  4. recordatorio breve de que deberá responder con claridad y reflexión
- Mantén los mensajes cortos y naturales.
- Devuelve exactamente un JSON válido.
- El JSON debe tener esta estructura:
{
  "messages": [
    { "role": "assistant", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}
- No agregues texto antes ni después del JSON.
TXT;

        // Extrae y limpia campos del contenido del reto.
        // safeString evita valores sucios o null.
        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');
        $timeMin = (int)($challengeContent['timeMin'] ?? 0);
        $timeMax = (int)($challengeContent['timeMax'] ?? 0);
        $maxPoints = (int)($challengeContent['maxPoints'] ?? 0);

        // "input" representa los datos dinámicos del reto actual.
        // Mientras instructions define las reglas del modelo,
        // input le dice sobre qué reto concreto debe trabajar.
        $input = <<<TXT
Datos del reto:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título del reto: {$challengeTitle}
- Descripción: {$description}
- Objetivo: {$objective}
- Dificultad: {$difficultyLabel}
- Tiempo estimado: {$timeMin} a {$timeMax} minutos
- Puntaje máximo: {$maxPoints}

Genera los mensajes iniciales del reto.
TXT;

        // Envía instructions + input al servicio OpenAI.
        // El resultado esperado es texto JSON.
        $raw = $this->openAIService->generateText($instructions, $input);

        // Intenta decodificar la respuesta JSON a array asociativo.
        $decoded = json_decode($raw, true);

        // Valida que exista la estructura "messages" y que sea un arreglo.
        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para los mensajes iniciales del reto.'
        );

        // Normaliza los mensajes para dejarlos en el formato exacto
        // que espera el frontend.
        return $this->normalizeMessages($payload['messages'], 'msg_ai_intro_');
    }

    /**
     * Genera la consigna principal del reto.
     */
    public function generateChallengePromptMessages(
        string $challengeTitle,
        string $skillName,
        array $challengeContent,
        string $userName = ''
    ): array {
        // Prompt del sistema para construir la consigna principal.
        // Aquí se le pide a la IA que NO evalúe todavía,
        // sino que solo plantee la actividad.
        $instructions = <<<TXT
Eres el asistente virtual de SkillView.

Tu tarea es generar SOLO la consigna principal de un reto de habilidades blandas.

Reglas:
- Responde en español.
- Tono claro, cercano y profesional.
- No uses markdown.
- Debes presentar una consigna concreta, práctica y fácil de entender.
- La consigna debe estar alineada con la habilidad trabajada y con la descripción del reto.
- No des la respuesta ideal.
- No evalúes todavía.
- Puedes usar 1 o 2 mensajes del asistente como máximo.
- Indica claramente qué debe hacer el usuario en su respuesta.
- Devuelve exactamente un JSON válido.
- El JSON debe tener esta estructura:
{
  "messages": [
    { "role": "assistant", "text": "..." }
  ]
}
- No agregues texto antes ni después del JSON.
TXT;

        // Se limpian y preparan los datos del reto.
        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');

        // Convierte tags de array a string separado por comas.
        $tags = $this->implodeArray($challengeContent['tags'] ?? []);

        // Datos dinámicos concretos del reto actual.
        $input = <<<TXT
Datos del reto:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título del reto: {$challengeTitle}
- Descripción: {$description}
- Objetivo: {$objective}
- Acción esperada: {$expectedAction}
- Dificultad: {$difficultyLabel}
- Tags: {$tags}

Genera la consigna principal del reto para que el usuario responda.
TXT;

        // Solicita a OpenAI la consigna principal.
        $raw = $this->openAIService->generateText($instructions, $input);

        // Decodifica la respuesta.
        $decoded = json_decode($raw, true);

        // Valida que la estructura sea correcta.
        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para la consigna principal del reto.'
        );

        // Devuelve mensajes ya normalizados.
        return $this->normalizeMessages($payload['messages'], 'msg_ai_prompt_');
    }

    /**
     * Evalúa la respuesta principal del usuario en el reto.
     */
    public function evaluateChallengeAnswer(
        string $challengeTitle,
        string $skillName,
        string $userMessage,
        array $challengeContent,
        array $challengeContext = [],
        string $userName = ''
    ): array {
        // Prompt del sistema para evaluación.
        // Este es el bloque más importante del servicio porque define:
        // - criterios de evaluación,
        // - formato JSON,
        // - niveles de desempeño,
        // - scoreRatio,
        // - y reglas para feedbackSummary.
        //
        // Aquí la IA actúa como evaluador del reto.
        $instructions = <<<TXT
Eres el evaluador virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.

Tu tarea es evaluar la respuesta de un usuario en un reto corto de habilidades blandas.

Debes decidir si la respuesta:
- cumple el reto
- necesita reintento
- muestra suficiente coherencia, pertinencia y aplicación práctica
- merece una valoración de desempeño

Criterios de evaluación:
1. Pertinencia: responde realmente a la consigna.
2. Coherencia: la respuesta tiene sentido y está bien construida.
3. Concreción: evita quedarse en frases vacías o demasiado genéricas.
4. Aplicación práctica: muestra cómo actuaría, reformularía o respondería el usuario.
5. Alineación con la habilidad: refleja la habilidad trabajada.

Reglas:
- Responde en español.
- No uses markdown.
- Sé justo pero exigente.
- No aceptes respuestas vacías, incoherentes, fuera de tema o demasiado genéricas.
- Si la respuesta no cumple, debes pedir reintento con feedback breve y útil.
- Si la respuesta sí cumple, marca accepted = true.
- Si accepted = false, normalmente scoreRatio debe ser 0.
- No reveles una respuesta modelo completa.
- El feedback debe ser breve, claro y útil.
- feedbackSummary debe ser específico y útil, no genérico.
- feedbackSummary debe explicar brevemente qué hizo bien el usuario o qué le faltó.
- Si la respuesta fue aceptada, feedbackSummary debe mencionar al menos 1 fortaleza concreta.
- Si la respuesta fue rechazada, feedbackSummary debe mencionar la principal carencia.
- Devuelve exactamente un JSON válido.
- No agregues texto antes ni después del JSON.

El JSON debe tener exactamente esta estructura:
{
  "accepted": true,
  "needsRetry": false,
  "retryReason": null,
  "detectedIssues": [],
  "scoreRatio": 0.0,
  "performanceLevel": "ACCEPTABLE",
  "feedbackSummary": "...",
  "messages": [
    { "role": "assistant", "text": "..." }
  ]
}

Reglas del formato:
- accepted: boolean
- needsRetry: boolean
- retryReason: string o null
- detectedIssues: array de strings
- scoreRatio: número entre 0 y 1
- performanceLevel: uno de estos valores exactos:
  EXCELLENT
  GOOD
  ACCEPTABLE
  INSUFFICIENT
- retryReason solo puede ser uno de estos valores cuando aplique:
  TOO_GENERIC
  OFF_TOPIC
  INCOHERENT
  LOW_REFLECTION
  NO_ACTIONABLE_IDEA
  INSUFFICIENT_DEVELOPMENT
- messages siempre debe ser un array
- Si accepted = true:
  needsRetry = false
  retryReason = null
- Si accepted = false:
  needsRetry = true
  performanceLevel = INSUFFICIENT

Guía de scoreRatio:
- EXCELLENT: entre 0.90 y 1.00
- GOOD: entre 0.70 y 0.89
- ACCEPTABLE: entre 0.50 y 0.69
- INSUFFICIENT: entre 0.00 y 0.49

Solo marca accepted = true cuando la respuesta sea al menos ACCEPTABLE.

Guía para feedbackSummary:
- Debe tener entre 1 y 2 frases.
- Debe sonar natural y profesional.
- Debe evitar frases vacías como:
  "Buen trabajo"
  "Respuesta correcta"
  "La respuesta fue buena"
- Debe decir concretamente qué aportó valor o qué faltó.
TXT;

        // Extrae y limpia contenido del reto.
        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');
        $maxPoints = (int)($challengeContent['maxPoints'] ?? 0);

        // Extrae contexto del flujo:
        // intento actual y máximo de intentos.
        $attemptNumber = (int)($challengeContext['attemptNumber'] ?? 1);
        $maxAttempts = (int)($challengeContext['maxAttempts'] ?? 3);

        // Input con el contexto real de evaluación.
        $input = <<<TXT
Datos del reto:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título del reto: {$challengeTitle}
- Descripción del reto: {$description}
- Objetivo: {$objective}
- Acción esperada: {$expectedAction}
- Dificultad: {$difficultyLabel}
- Puntaje máximo disponible: {$maxPoints}

Contexto adicional:
- Intento actual: {$attemptNumber}
- Máximo de intentos: {$maxAttempts}

Respuesta del usuario:
{$userMessage}

Evalúa la respuesta del usuario.
TXT;

        // Solicita a OpenAI la evaluación de la respuesta.
        $raw = $this->openAIService->generateText($instructions, $input);

        // Decodifica el JSON.
        $decoded = json_decode($raw, true);

        // Si la respuesta no es JSON válido, lanza excepción.
        if (!is_array($decoded)) {
            throw new \Exception('La IA no devolvió un JSON válido para evaluar el reto.');
        }

        // Valida y normaliza el payload de evaluación.
        return $this->validateEvaluationPayload($decoded);
    }

    /**
     * Genera el cierre final del reto ya aprobado.
     */
    public function generateFinalFeedbackMessages(
        string $challengeTitle,
        string $skillName,
        string $userMessage,
        array $evaluationResult,
        array $challengeContent,
        string $userName = ''
    ): array {
        // Prompt del sistema para el cierre exitoso del reto.
        // En esta etapa la IA ya NO evalúa desde cero;
        // solo construye el feedback final para el modal.
        $instructions = <<<TXT
Eres el tutor virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.
Tu tarea es generar SOLO los mensajes finales de retroalimentación y cierre de un reto ya completado exitosamente.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional, breve y motivador.
- No uses markdown.
- No abras nuevas preguntas.
- No repitas literalmente toda la respuesta del estudiante.
- Debes:
  1. reconocer que completó el reto,
  2. dar una retroalimentación final breve basada en su desempeño,
  3. resaltar de forma concreta qué hizo bien,
  4. cerrar con una recomendación breve para seguir fortaleciendo la habilidad.
- El cierre debe sentirse positivo, útil y estructurado.
- Devuelve exactamente un JSON válido.
- El JSON debe tener esta estructura:
{
  "messages": [
    { "role": "assistant", "text": "..." },
    { "role": "assistant", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}
- No agregues texto antes ni después del JSON.
- No devuelvas mensajes con role "user".
- Todos los mensajes deben pertenecer al asistente.
- Evita mensajes genéricos como "buen trabajo" sin explicación; cada mensaje debe aportar algo útil.
TXT;

        // Datos del reto que ayudan a contextualizar el cierre.
        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');

        // Datos de evaluación ya obtenidos previamente.
        $performanceLevel = $this->safeString($evaluationResult['performanceLevel'] ?? 'ACCEPTABLE');
        $feedbackSummary = $this->safeString($evaluationResult['feedbackSummary'] ?? '');
        $scoreAwarded = (int)($evaluationResult['scoreAwarded'] ?? 0);
        $maxScore = (int)($evaluationResult['maxScore'] ?? 0);

        // Input de cierre final.
        $input = <<<TXT
Datos del reto completado:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título del reto: {$challengeTitle}
- Descripción del reto: {$description}
- Objetivo: {$objective}
- Acción esperada: {$expectedAction}

Desempeño del estudiante:
- Nivel de desempeño: {$performanceLevel}
- Resumen de evaluación: {$feedbackSummary}
- Puntaje obtenido: {$scoreAwarded}
- Puntaje máximo: {$maxScore}

Respuesta final del estudiante:
{$userMessage}

Genera los mensajes finales de retroalimentación y cierre del reto.
TXT;

        // Solicita a la IA el feedback final.
        $raw = $this->openAIService->generateText($instructions, $input);

        // Decodifica la respuesta.
        $decoded = json_decode($raw, true);

        // Valida estructura base del JSON.
        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para el feedback final del reto.'
        );

        // Se crea un array final de mensajes normalizados manualmente.
        $messages = [];

        foreach ($payload['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));
            $role = trim((string)($msg['role'] ?? 'assistant'));

            // Ignora mensajes vacíos.
            if ($text === '') {
                continue;
            }

            // Obliga a que todos los mensajes sean del asistente.
            if ($role !== 'assistant') {
                $role = 'assistant';
            }

            // Inserta el mensaje en formato esperado por frontend.
            $messages[] = [
                'id' => 'msg_ai_ff_' . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text
            ];
        }

        // Si después del filtrado no quedó ningún mensaje, se lanza excepción.
        if (empty($messages)) {
            throw new \Exception('La IA devolvió mensajes vacíos para el feedback final del reto.');
        }

        return $messages;
    }

    /**
     * Normaliza mensajes al formato que espera el frontend.
     */
    public function normalizeMessages(array $messages, string $idPrefix = 'msg_ai_'): array
    {
        // Array donde se guardarán los mensajes ya limpios.
        $normalized = [];

        foreach ($messages as $index => $msg) {
            // Si un elemento no es array, se ignora.
            if (!is_array($msg)) {
                continue;
            }

            // Obtiene texto del mensaje y lo limpia.
            $text = trim((string)($msg['text'] ?? ''));

            // Ignora mensajes sin texto.
            if ($text === '') {
                continue;
            }

            // Obtiene el role; si viene vacío, usa assistant por defecto.
            $role = trim((string)($msg['role'] ?? 'assistant'));
            if ($role === '') {
                $role = 'assistant';
            }

            // Inserta el mensaje con:
            // - id único,
            // - role,
            // - type = text,
            // - text limpio.
            $normalized[] = [
                'id' => $idPrefix . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text,
            ];
        }

        // Si no quedó ningún mensaje válido, se lanza excepción.
        if (empty($normalized)) {
            throw new \Exception('La IA devolvió mensajes vacíos.');
        }

        return $normalized;
    }

    /**
     * Valida payloads que solo contienen un array "messages".
     */
    private function validateMessagesPayload(array $decoded, string $errorMessage): array
    {
        // Si no existe "messages" o no es array, se considera inválido.
        if (empty($decoded['messages']) || !is_array($decoded['messages'])) {
            throw new \Exception($errorMessage);
        }

        return $decoded;
    }

    /**
     * Valida y normaliza el payload de evaluación del reto.
     */
    private function validateEvaluationPayload(array $decoded): array
    {
        // Lee accepted; si no existe, por defecto false.
        $accepted = (bool)($decoded['accepted'] ?? false);

        // needsRetry por defecto depende de si fue aceptado o no.
        $needsRetry = (bool)($decoded['needsRetry'] ?? !$accepted);

        // Normaliza retryReason según lista permitida.
        $retryReason = $this->normalizeRetryReason($decoded['retryReason'] ?? null);

        // Si detectedIssues es array, lo conserva; si no, usa array vacío.
        $detectedIssues = is_array($decoded['detectedIssues'] ?? null) ? array_values($decoded['detectedIssues']) : [];

        // Normaliza scoreRatio entre 0 y 1.
        $scoreRatio = $this->clampScoreRatio($decoded['scoreRatio'] ?? 0);

        // Normaliza performanceLevel según lista permitida.
        $performanceLevel = $this->normalizePerformanceLevel($decoded['performanceLevel'] ?? null);

        // Limpia feedbackSummary.
        $feedbackSummary = trim((string)($decoded['feedbackSummary'] ?? ''));

        // Conserva messages solo si es array.
        $messages = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];

        // Si la respuesta fue aceptada:
        if ($accepted) {
            // No debe requerir retry.
            $needsRetry = false;

            // No debe tener retryReason.
            $retryReason = null;

            // Si la IA devolvió un performance inválido de tipo insuficiente,
            // se corrige a ACCEPTABLE para mantener consistencia.
            if ($performanceLevel === 'INSUFFICIENT') {
                $performanceLevel = 'ACCEPTABLE';
            }

            // Si accepted=true pero el scoreRatio viene menor a 0.5,
            // se corrige al mínimo aceptable.
            if ($scoreRatio < 0.5) {
                $scoreRatio = 0.5;
            }

            // Si el feedbackSummary vino vacío, se pone uno por defecto.
            if ($feedbackSummary === '') {
                $feedbackSummary = 'La respuesta fue suficiente para completar el reto y mostró una aplicación adecuada de la habilidad trabajada.';
            }
        } else {
            // Si no fue aceptada, siempre debe requerir retry.
            $needsRetry = true;

            // El performance debe ser insuficiente.
            $performanceLevel = 'INSUFFICIENT';

            // El scoreRatio de una respuesta rechazada se fuerza a 0.
            $scoreRatio = 0.0;

            // Si no vino retryReason, se usa TOO_GENERIC como fallback.
            if ($retryReason === null) {
                $retryReason = 'TOO_GENERIC';
            }

            // Si el feedbackSummary viene vacío, se completa con uno base.
            if ($feedbackSummary === '') {
                $feedbackSummary = 'La respuesta no desarrolló lo suficiente la consigna y necesita mayor claridad o concreción.';
            }
        }

        // Devuelve el payload ya validado y normalizado.
        return [
            'accepted' => $accepted,
            'needsRetry' => $needsRetry,
            'retryReason' => $retryReason,
            'detectedIssues' => $detectedIssues,
            'scoreRatio' => $scoreRatio,
            'performanceLevel' => $performanceLevel,
            'feedbackSummary' => $feedbackSummary,
            'messages' => $messages,
        ];
    }

    // Normaliza el retryReason.
    // Si el valor no está en la lista permitida, se devuelve TOO_GENERIC.
    private function normalizeRetryReason($retryReason): ?string
    {
        // Si viene null, retorna null.
        if ($retryReason === null) {
            return null;
        }

        // Limpia y convierte a mayúsculas.
        $retryReason = strtoupper(trim((string)$retryReason));

        // Si quedó vacío, retorna null.
        if ($retryReason === '') {
            return null;
        }

        // Si está permitido, lo devuelve.
        // Si no, usa TOO_GENERIC como fallback seguro.
        return in_array($retryReason, self::ALLOWED_RETRY_REASONS, true)
            ? $retryReason
            : 'TOO_GENERIC';
    }

    // Normaliza el nivel de desempeño.
    // Si no coincide con la lista permitida, retorna INSUFFICIENT.
    private function normalizePerformanceLevel($performanceLevel): string
    {
        $performanceLevel = strtoupper(trim((string)$performanceLevel));

        if (in_array($performanceLevel, self::ALLOWED_PERFORMANCE_LEVELS, true)) {
            return $performanceLevel;
        }

        return 'INSUFFICIENT';
    }

    // Limita el scoreRatio para que siempre quede entre 0 y 1.
    private function clampScoreRatio($value): float
    {
        $ratio = is_numeric($value) ? (float)$value : 0.0;

        if ($ratio < 0) {
            return 0.0;
        }

        if ($ratio > 1) {
            return 1.0;
        }

        return $ratio;
    }

    // Convierte un array en string separado por comas.
    // Además limpia espacios y elimina elementos vacíos.
    private function implodeArray(array $items): string
    {
        $clean = array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $items));

        return implode(', ', $clean);
    }

    // Helper simple para limpiar cualquier valor y convertirlo a string seguro.
    private function safeString($value): string
    {
        return trim((string)$value);
    }
}