<?php

namespace Classes;

class ChallengeAIService
{
    private OpenAIService $openAIService;

    private const ALLOWED_RETRY_REASONS = [
        'TOO_GENERIC',
        'OFF_TOPIC',
        'INCOHERENT',
        'LOW_REFLECTION',
        'NO_ACTIONABLE_IDEA',
        'INSUFFICIENT_DEVELOPMENT',
    ];

    private const ALLOWED_PERFORMANCE_LEVELS = [
        'EXCELLENT',
        'GOOD',
        'ACCEPTABLE',
        'INSUFFICIENT',
    ];

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

        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');
        $timeMin = (int)($challengeContent['timeMin'] ?? 0);
        $timeMax = (int)($challengeContent['timeMax'] ?? 0);
        $maxPoints = (int)($challengeContent['maxPoints'] ?? 0);

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

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para los mensajes iniciales del reto.'
        );

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

        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');
        $tags = $this->implodeArray($challengeContent['tags'] ?? []);

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

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para la consigna principal del reto.'
        );

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

        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');
        $difficultyLabel = $this->safeString($challengeContent['difficultyLabel'] ?? '');
        $maxPoints = (int)($challengeContent['maxPoints'] ?? 0);

        $attemptNumber = (int)($challengeContext['attemptNumber'] ?? 1);
        $maxAttempts = (int)($challengeContext['maxAttempts'] ?? 3);

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

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \Exception('La IA no devolvió un JSON válido para evaluar el reto.');
        }

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

        $description = $this->safeString($challengeContent['description'] ?? '');
        $objective = $this->safeString($challengeContent['objective'] ?? '');
        $expectedAction = $this->safeString($challengeContent['expectedAction'] ?? '');

        $performanceLevel = $this->safeString($evaluationResult['performanceLevel'] ?? 'ACCEPTABLE');
        $feedbackSummary = $this->safeString($evaluationResult['feedbackSummary'] ?? '');
        $scoreAwarded = (int)($evaluationResult['scoreAwarded'] ?? 0);
        $maxScore = (int)($evaluationResult['maxScore'] ?? 0);

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

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        $payload = $this->validateMessagesPayload(
            $decoded,
            'La IA no devolvió un JSON válido para el feedback final del reto.'
        );

        $messages = [];
        foreach ($payload['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));
            $role = trim((string)($msg['role'] ?? 'assistant'));

            if ($text === '') {
                continue;
            }

            if ($role !== 'assistant') {
                $role = 'assistant';
            }

            $messages[] = [
                'id' => 'msg_ai_ff_' . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text
            ];
        }

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
        $normalized = [];

        foreach ($messages as $index => $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $text = trim((string)($msg['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $role = trim((string)($msg['role'] ?? 'assistant'));
            if ($role === '') {
                $role = 'assistant';
            }

            $normalized[] = [
                'id' => $idPrefix . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text,
            ];
        }

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
        $accepted = (bool)($decoded['accepted'] ?? false);
        $needsRetry = (bool)($decoded['needsRetry'] ?? !$accepted);

        $retryReason = $this->normalizeRetryReason($decoded['retryReason'] ?? null);
        $detectedIssues = is_array($decoded['detectedIssues'] ?? null) ? array_values($decoded['detectedIssues']) : [];
        $scoreRatio = $this->clampScoreRatio($decoded['scoreRatio'] ?? 0);
        $performanceLevel = $this->normalizePerformanceLevel($decoded['performanceLevel'] ?? null);
        $feedbackSummary = trim((string)($decoded['feedbackSummary'] ?? ''));
        $messages = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];

        if ($accepted) {
            $needsRetry = false;
            $retryReason = null;

            if ($performanceLevel === 'INSUFFICIENT') {
                $performanceLevel = 'ACCEPTABLE';
            }

            if ($scoreRatio < 0.5) {
                $scoreRatio = 0.5;
            }

            if ($feedbackSummary === '') {
                $feedbackSummary = 'La respuesta fue suficiente para completar el reto y mostró una aplicación adecuada de la habilidad trabajada.';
            }
        } else {
            $needsRetry = true;
            $performanceLevel = 'INSUFFICIENT';
            $scoreRatio = 0.0;

            if ($retryReason === null) {
                $retryReason = 'TOO_GENERIC';
            }

            if ($feedbackSummary === '') {
                $feedbackSummary = 'La respuesta no desarrolló lo suficiente la consigna y necesita mayor claridad o concreción.';
            }
        }

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

    private function normalizeRetryReason($retryReason): ?string
    {
        if ($retryReason === null) {
            return null;
        }

        $retryReason = strtoupper(trim((string)$retryReason));
        if ($retryReason === '') {
            return null;
        }

        return in_array($retryReason, self::ALLOWED_RETRY_REASONS, true)
            ? $retryReason
            : 'TOO_GENERIC';
    }

    private function normalizePerformanceLevel($performanceLevel): string
    {
        $performanceLevel = strtoupper(trim((string)$performanceLevel));

        if (in_array($performanceLevel, self::ALLOWED_PERFORMANCE_LEVELS, true)) {
            return $performanceLevel;
        }

        return 'INSUFFICIENT';
    }

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

    private function implodeArray(array $items): string
    {
        $clean = array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $items));

        return implode(', ', $clean);
    }

    private function safeString($value): string
    {
        return trim((string)$value);
    }
}
