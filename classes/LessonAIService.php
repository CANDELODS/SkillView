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

        // Transformamos la respuesta de la IA al formato que espera tu frontend
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
}