<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    $message = strtolower(trim((string)($payload['message'] ?? '')));

    if ($message === '') {
        throw new RuntimeException('Message required.');
    }

    $reply = "Terima kasih telah menghubungi layanan konsultasi apotek.\n\n";

    if (str_contains($message, 'demam')) {
        $reply .= "Gejala demam ringan dapat dibantu dengan Paracetamol dan istirahat cukup. Bila demam lebih dari 3 hari segera konsultasi dokter.\n\n";
    }

    if (str_contains($message, 'batuk')) {
        $reply .= "Untuk batuk ringan dapat mempertimbangkan obat batuk herbal dan memperbanyak minum hangat.\n\n";
    }

    if (str_contains($message, 'antibiotik')) {
        $reply .= "Antibiotik memerlukan konsultasi apoteker atau dokter.\n\n";
    }

    $reply .= "AI assistant ini tidak menggantikan dokter atau apoteker profesional.";

    echo json_encode([
        'success' => true,
        'reply' => $reply,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
