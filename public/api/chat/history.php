<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Helpers/ApiBootstrap.php';

use App\Helpers\Response;
use App\Models\ConversationModel;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$branchId = (int)($_GET['branch_id'] ?? 0);
$sessionId = trim((string)($_GET['session_id'] ?? session_id()));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

if ($branchId <= 0) {
    Response::error('branch_id is required');
}

if ($sessionId === '') {
    Response::error('session_id is required');
}

$sessionKey = hash('sha256', "web:{$branchId}:{$sessionId}");
$conversationModel = new ConversationModel();
$conversation = $conversationModel->findActiveBySessionKey($sessionKey);

if (!$conversation) {
    Response::success([
        'conversation' => null,
        'messages' => [],
    ], 'OK');
}

$messages = array_map(static function (array $row): array {
    $rawData = [];
    if (!empty($row['raw_data'])) {
        $decoded = json_decode((string)$row['raw_data'], true);
        if (is_array($decoded)) {
            $rawData = $decoded;
        }
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'sender' => (string)($row['sender'] ?? ''),
        'message' => (string)($row['message'] ?? ''),
        'intent' => (string)($row['intent'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'raw_data' => $rawData,
    ];
}, $conversationModel->getMessages((int)$conversation['id'], $limit));

Response::success([
    'conversation' => [
        'id' => (int)($conversation['id'] ?? 0),
        'state' => (string)($conversation['state'] ?? 'idle'),
        'started_at' => (string)($conversation['started_at'] ?? ''),
        'last_activity' => (string)($conversation['last_activity'] ?? ''),
    ],
    'messages' => $messages,
], 'OK');
