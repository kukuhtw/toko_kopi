<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Services/AboutUsAiService.php';

use App\Services\AboutUsAiService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['business_name'])) {
        throw new RuntimeException('business_name required.');
    }

    $service = new AboutUsAiService();

    $prompt = $service->buildPrompt($payload);
    $generated = $service->mockGenerate($payload);

    echo json_encode([
        'success' => true,
        'prompt' => $prompt,
        'generated_title' => $generated['title'],
        'generated_content' => $generated['content'],
        'model' => getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4.1',
        'status' => 'draft',
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
