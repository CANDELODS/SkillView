<?php

namespace Classes;

class LessonAIService
{
    // Servicio de más bajo nivel que se encarga de hablar con OpenAI
    private OpenAIService $openAIService;

    public function __construct()
    {
        // Inyectamos / creamos el servicio que hace la llamada HTTP real
        $this->openAIService = new OpenAIService();
    }

    /**
     * Genera los mensajes iniciales de una lección usando IA.
     *
     * @param string $lessonTitle Título de la lección
     * @param string $skillName Nombre de la habilidad blanda
     * @param array $content Contenido parseado de la lección (objetivo, conceptos, errores, etc.)
     * @param string $userName Nombre del usuario para personalizar la bienvenida
     * @return array Lista de mensajes en el formato que espera el frontend
     * @throws \Exception Si OpenAI responde mal, devuelve JSON inválido o mensajes vacíos
     */
    public function generateInitialMessages(
        string $lessonTitle,
        string $skillName,
        array $content,
        string $userName = ''
    ): array {
        // Instrucciones del sistema para el modelo.
        // Aquí le decimos a la IA cómo comportarse y en qué formato responder.
        $instructions = <<<TXT
Eres el tutor virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.
Tu tarea es generar SOLO los mensajes iniciales de una lección.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional y pedagógico.
- No uses markdown.
- No hagas todavía preguntas al usuario.
- Solo introduce la lección y explica brevemente:
  1. bienvenida
  2. objetivo
  3. conceptos clave
  4. errores comunes
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

        // Preparamos el contenido dinámico de la lección
        $objective = $content['objective'] ?? '';
        $keyConcepts = !empty($content['keyConcepts']) ? implode(', ', $content['keyConcepts']) : '';
        $commonMistakes = !empty($content['commonMistakes']) ? implode(', ', $content['commonMistakes']) : '';

        // Construimos el input de la tarea concreta que se enviará a OpenAI
        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Objetivo: {$objective}
- Conceptos clave: {$keyConcepts}
- Errores comunes: {$commonMistakes}

Genera los mensajes iniciales.
TXT;

        // Llamamos al servicio de OpenAI y obtenemos texto crudo
        $raw = $this->openAIService->generateText($instructions, $input);

        // Intentamos convertir ese texto en JSON
        $decoded = json_decode($raw, true);

        // Validamos que venga la estructura esperada
        if (!is_array($decoded) || empty($decoded['messages']) || !is_array($decoded['messages'])) {
            throw new \Exception('La IA no devolvió un JSON válido para los mensajes iniciales.');
        }

        // Transformamos la respuesta de la IA al formato que espera el frontend
        $messages = [];
        foreach ($decoded['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));

            // Si algún mensaje vino vacío, lo ignoramos
            if ($text === '') {
                continue;
            }

            $messages[] = [
                'id' => 'msg_ai_' . ($index + 1) . '_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $text
            ];
        }

        // Si después de limpiar no quedó ningún mensaje, lanzamos error
        if (empty($messages)) {
            throw new \Exception('La IA devolvió mensajes vacíos.');
        }

        return $messages;
    }

    /**
     * Genera los mensajes que abren la micro-práctica o escenario práctico.
     *
     * @param string $lessonTitle Título de la lección
     * @param string $skillName Nombre de la habilidad
     * @param array $content Contenido parseado de la lección
     * @param string $lessonType Tipo de lección: standard o integrator
     * @param string $userName Nombre del usuario
     * @return array Lista de mensajes en el formato que espera el frontend
     * @throws \Exception Si la IA devuelve un JSON inválido o vacío
     */
    public function generateMicroPracticePromptMessages(
        string $lessonTitle,
        string $skillName,
        array $content,
        string $lessonType = 'standard',
        string $userName = ''
    ): array {
        $instructions = <<<TXT
Eres el tutor virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.
Tu tarea es generar SOLO los mensajes que introducen la parte práctica de una lección.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional y pedagógico.
- No uses markdown.
- Debes preparar al estudiante para responder.
- Si la lección es estándar, introduce la micro-práctica y formula la pregunta práctica.
- Si la lección es integradora, introduce el escenario y luego pide al estudiante que responda.
- No evalúes todavía la respuesta del usuario.
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

        $objective = $content['objective'] ?? '';
        $microPractice = $content['microPractice'] ?? '';
        $scenario = $content['scenario'] ?? '';
        $expectedSummary = $content['expectedSummary'] ?? '';

        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Tipo de lección: {$lessonType}
- Objetivo: {$objective}
- Micro-práctica: {$microPractice}
- Escenario: {$scenario}
- Resumen esperado: {$expectedSummary}

Genera los mensajes para abrir la parte práctica.
TXT;

        $raw = $this->openAIService->generateText($instructions, $input);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || empty($decoded['messages']) || !is_array($decoded['messages'])) {
            throw new \Exception('La IA no devolvió un JSON válido para la micro-práctica.');
        }

        $messages = [];
        foreach ($decoded['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $messages[] = [
                'id' => 'msg_ai_mp_' . ($index + 1) . '_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $text
            ];
        }

        if (empty($messages)) {
            throw new \Exception('La IA devolvió mensajes vacíos para la micro-práctica.');
        }

        return $messages;
    }

    public function evaluateMicroPracticeAnswer(
        string $lessonTitle,
        string $skillName,
        string $userMessage,
        array $content,
        string $lessonType = 'standard',
        string $userName = ''
    ): array {
        $instructions = <<<TXT
Eres el tutor virtual de SkillView.
Tu tarea es evaluar la respuesta del estudiante en la micro-práctica.

Debes decidir si la respuesta:
- responde la consigna,
- aporta algo concreto,
- evita evasivas,
- muestra una mínima reflexión o ejemplo.

Reglas:
- Responde en español.
- No uses markdown.
- Si la respuesta es evasiva, defensiva, fuera de contexto o demasiado genérica, NO debe aceptarse.
- Si la respuesta cumple lo mínimo, sí puede aceptarse.
- Devuelve exactamente un JSON válido con esta estructura:

{
  "accepted": true,
  "needsRetry": false,
  "retryReason": null,
  "detectedIssues": [],
  "messages": [
    { "role": "user", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}

retryReason debe ser uno de:
- TOO_GENERIC
- EVASIVE
- DEFENSIVE
- OFF_TOPIC
- NO_EXAMPLE
- LOW_REFLECTION

No agregues texto antes ni después del JSON.
TXT;

        $objective = $content['objective'] ?? '';
        $microPractice = $content['microPractice'] ?? '';
        $expectedSummary = $content['expectedSummary'] ?? '';
        $scenario = $content['scenario'] ?? '';

        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Tipo de lección: {$lessonType}
- Objetivo: {$objective}
- Micro-práctica: {$microPractice}
- Escenario: {$scenario}
- Resumen esperado: {$expectedSummary}

Respuesta del estudiante:
{$userMessage}

Evalúa la respuesta y devuelve el JSON solicitado.
TXT;

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \Exception('La IA no devolvió un JSON válido para evaluar la micro-práctica.');
        }

        return [
            'accepted' => (bool)($decoded['accepted'] ?? false),
            'needsRetry' => (bool)($decoded['needsRetry'] ?? true),
            'retryReason' => $decoded['retryReason'] ?? 'TOO_GENERIC',
            'detectedIssues' => is_array($decoded['detectedIssues'] ?? null) ? $decoded['detectedIssues'] : [],
            'messages' => is_array($decoded['messages'] ?? null) ? $decoded['messages'] : []
        ];
    }

    /**
     * Genera los mensajes que conectan la respuesta del usuario
     * con la mini-evaluación de la lección.
     *
     * @param string $lessonTitle Título de la lección
     * @param string $skillName Nombre de la habilidad
     * @param string $userMessage Respuesta del usuario en la micro-práctica
     * @param array $content Contenido parseado de la lección
     * @param string $lessonType Tipo de lección: standard o integrator
     * @param string $userName Nombre del usuario
     * @return array Lista de mensajes en el formato que espera el frontend
     * @throws \Exception Si la IA devuelve JSON inválido o mensajes vacíos
     */
    public function generateMiniEvaluationMessages(
        string $lessonTitle,
        string $skillName,
        string $userMessage,
        array $content,
        string $lessonType = 'standard',
        string $userName = ''
    ): array {
        $instructions = <<<TXT
Eres el tutor virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.
Tu tarea es generar SOLO el bloque de mensajes posterior a la primera respuesta del estudiante.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional y pedagógico.
- No uses markdown.
- Primero reconoce brevemente la respuesta del estudiante.
- Luego ofrece un feedback corto, útil y positivo.
- Después formula la mini-evaluación.
- No des todavía la respuesta correcta.
- No cierres la lección.
- Si la lección es estándar, usa el bloque de mini-evaluación.
- Si la lección es integradora, usa los criterios de evaluación para plantear la siguiente reflexión o pregunta.
- Devuelve exactamente un JSON válido.
- El JSON debe tener esta estructura:
{
  "messages": [
    { "role": "user", "text": "..." },
    { "role": "assistant", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}
- No agregues texto antes ni después del JSON.
TXT;

        $objective = $content['objective'] ?? '';
        $miniEvaluation = $content['miniEvaluation'] ?? '';
        $expectedSummary = $content['expectedSummary'] ?? '';
        $evaluationCriteria = !empty($content['evaluationCriteria'])
            ? implode(', ', $content['evaluationCriteria'])
            : '';

        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Tipo de lección: {$lessonType}
- Objetivo: {$objective}
- Respuesta del estudiante: {$userMessage}
- Mini-evaluación: {$miniEvaluation}
- Criterios de evaluación: {$evaluationCriteria}
- Resumen esperado: {$expectedSummary}

Genera los mensajes para dar feedback breve y formular la mini-evaluación.
TXT;

        $raw = $this->openAIService->generateText($instructions, $input);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || empty($decoded['messages']) || !is_array($decoded['messages'])) {
            throw new \Exception('La IA no devolvió un JSON válido para la mini-evaluación.');
        }

        $messages = [];
        foreach ($decoded['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));
            $role = trim((string)($msg['role'] ?? 'assistant'));

            if ($text === '') {
                continue;
            }

            if (!in_array($role, ['assistant', 'user'], true)) {
                $role = 'assistant';
            }

            $messages[] = [
                'id' => 'msg_ai_me_' . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text
            ];
        }

        if (empty($messages)) {
            throw new \Exception('La IA devolvió mensajes vacíos para la mini-evaluación.');
        }

        return $messages;
    }

    public function evaluateMiniEvaluationAnswer(
        string $lessonTitle,
        string $skillName,
        string $userMessage,
        array $content,
        array $answers = [],
        string $lessonType = 'standard',
        string $userName = ''
    ): array {
        $instructions = <<<TXT
Eres el tutor virtual de SkillView.
Tu tarea es evaluar la respuesta del estudiante en la mini-evaluación.

Debes decidir si la respuesta:
- responde directamente a la consigna de la mini-evaluación,
- es coherente con la lección actual,
- muestra reflexión, criterio o justificación mínima,
- NO repite simplemente la respuesta dada antes en la micro-práctica,
- NO evade la consigna,
- NO responde fuera de contexto.

Reglas:
- Responde en español.
- No uses markdown.
- Si la respuesta es evasiva, fuera de contexto, demasiado genérica, repetida sin adaptación o no responde la mini-evaluación, NO debe aceptarse.
- Si la respuesta cumple lo mínimo, sí puede aceptarse.
- Devuelve exactamente un JSON válido con esta estructura:

{
  "accepted": true,
  "needsRetry": false,
  "retryReason": null,
  "detectedIssues": [],
  "messages": [
    { "role": "user", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}

retryReason debe ser uno de:
- TOO_GENERIC
- EVASIVE
- OFF_TOPIC
- NO_JUSTIFICATION
- REPEATED_PREVIOUS_ANSWER
- LOW_REFLECTION

No agregues texto antes ni después del JSON.
TXT;

        $objective = $content['objective'] ?? '';
        $miniEvaluation = $content['miniEvaluation'] ?? '';
        $expectedSummary = $content['expectedSummary'] ?? '';
        $evaluationCriteria = !empty($content['evaluationCriteria'])
            ? implode(', ', $content['evaluationCriteria'])
            : '';

        $microPracticeAnswer = trim((string)($answers['microPractice'] ?? ''));

        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Tipo de lección: {$lessonType}
- Objetivo: {$objective}
- Mini-evaluación: {$miniEvaluation}
- Criterios de evaluación: {$evaluationCriteria}
- Resumen esperado: {$expectedSummary}

Respuesta previa del estudiante en la micro-práctica:
{$microPracticeAnswer}

Respuesta actual del estudiante en la mini-evaluación:
{$userMessage}

Evalúa la respuesta actual y devuelve el JSON solicitado.
TXT;

        $raw = $this->openAIService->generateText($instructions, $input);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \Exception('La IA no devolvió un JSON válido para evaluar la mini-evaluación.');
        }

        return [
            'accepted' => (bool)($decoded['accepted'] ?? false),
            'needsRetry' => (bool)($decoded['needsRetry'] ?? true),
            'retryReason' => $decoded['retryReason'] ?? 'TOO_GENERIC',
            'detectedIssues' => is_array($decoded['detectedIssues'] ?? null) ? $decoded['detectedIssues'] : [],
            'messages' => is_array($decoded['messages'] ?? null) ? $decoded['messages'] : []
        ];
    }

    /**
     * Genera los mensajes finales de retroalimentación y cierre de la lección.
     *
     * @param string $lessonTitle Título de la lección
     * @param string $skillName Nombre de la habilidad
     * @param array $answers Respuestas del usuario (microPractice y miniEvaluation)
     * @param array $content Contenido parseado de la lección
     * @param string $lessonType Tipo de lección: standard o integrator
     * @param string $userName Nombre del usuario
     * @return array Lista de mensajes en el formato que espera el frontend
     * @throws \Exception Si la IA devuelve JSON inválido o mensajes vacíos
     */
    public function generateFinalFeedbackMessages(
        string $lessonTitle,
        string $skillName,
        array $answers,
        array $content,
        string $lessonType = 'standard',
        string $userName = ''
    ): array {
        $instructions = <<<TXT
Eres el tutor virtual de SkillView, una plataforma educativa para fortalecer habilidades blandas.
Tu tarea es generar SOLO los mensajes finales de retroalimentación y cierre de una lección.

Reglas:
- Responde en español.
- Tono cercano, claro, profesional, breve y pedagógico.
- No uses markdown.
- No abras nuevas preguntas.
- No repitas literalmente todo lo que dijo el estudiante.
- Debes:
  1. reconocer que completó la actividad,
  2. dar una retroalimentación final breve basada en sus respuestas,
  3. cerrar con un resumen claro de lo aprendido.
- El cierre debe sentirse positivo y útil.
- Devuelve exactamente un JSON válido.
- El JSON debe tener esta estructura:
{
  "messages": [
    { "role": "assistant", "text": "..." },
    { "role": "assistant", "text": "..." }
  ]
}
- No agregues texto antes ni después del JSON.
- No devuelvas mensajes con role "user".
- Todos los mensajes deben pertenecer al asistente.
TXT;

        $objective = $content['objective'] ?? '';
        $expectedSummary = $content['expectedSummary'] ?? '';
        $miniEvaluation = $content['miniEvaluation'] ?? '';
        $scenario = $content['scenario'] ?? '';

        $microPracticeAnswer = trim((string)($answers['microPractice'] ?? ''));
        $miniEvaluationAnswer = trim((string)($answers['miniEvaluation'] ?? ''));

        $input = <<<TXT
Datos de la lección:
- Nombre del usuario: {$userName}
- Habilidad: {$skillName}
- Título de la lección: {$lessonTitle}
- Tipo de lección: {$lessonType}
- Objetivo: {$objective}
- Mini-evaluación: {$miniEvaluation}
- Escenario: {$scenario}
- Resumen esperado: {$expectedSummary}

Respuestas del estudiante:
- Respuesta a micro-práctica: {$microPracticeAnswer}
- Respuesta a mini-evaluación: {$miniEvaluationAnswer}

Genera los mensajes finales de retroalimentación y cierre.
TXT;

        $raw = $this->openAIService->generateText($instructions, $input);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || empty($decoded['messages']) || !is_array($decoded['messages'])) {
            throw new \Exception('La IA no devolvió un JSON válido para el feedback final.');
        }

        $messages = [];
        foreach ($decoded['messages'] as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $messages[] = [
                'id' => 'msg_ai_ff_' . ($index + 1) . '_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $text
            ];
        }

        if (empty($messages)) {
            throw new \Exception('La IA devolvió mensajes vacíos para el feedback final.');
        }

        return $messages;
    }

    public function normalizeMessages(array $rawMessages, string $prefix = 'msg_ai_eval_'): array
    {
        $messages = [];

        foreach ($rawMessages as $index => $msg) {
            $text = trim((string)($msg['text'] ?? ''));
            $role = trim((string)($msg['role'] ?? 'assistant'));

            if ($text === '') {
                continue;
            }

            if (!in_array($role, ['assistant', 'user'], true)) {
                $role = 'assistant';
            }

            $messages[] = [
                'id' => $prefix . ($index + 1) . '_' . uniqid(),
                'role' => $role,
                'type' => 'text',
                'text' => $text
            ];
        }

        return $messages;
    }
}
