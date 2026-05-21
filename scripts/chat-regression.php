<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Config/config.php';

use App\Config\Database;
use App\Services\ChatEntityExtractor;
use App\Services\CustomerConversationService;

/**
 * Lightweight regression runner for common customer chat scenarios.
 *
 * Usage:
 *   php scripts/chat-regression.php
 *   php scripts/chat-regression.php --branch=1
 *   php scripts/chat-regression.php --verbose
 */

$options = getopt('', ['branch::', 'verbose']);
$branchId = isset($options['branch']) ? (int)$options['branch'] : findDefaultBranchId();
$verbose = array_key_exists('verbose', $options);

if ($branchId <= 0) {
    fwrite(STDERR, "No active branch found.\n");
    exit(1);
}

$extractor = new ChatEntityExtractor();
$service = new CustomerConversationService();

$cases = [
    [
        'name' => 'menu_description',
        'message' => 'apa itu americano',
        'assert' => static function (array $entities, array $result): array {
            return [
                expectEqual('intent', (string)($result['intent'] ?? ''), 'tanya_menu'),
                expectEqual('entity product', (string)($entities['products'][0]['name_candidate'] ?? ''), 'americano'),
                expectContains('reply contains product', (string)($result['reply_message'] ?? ''), 'Americano'),
            ];
        },
    ],
    [
        'name' => 'budget_lookup',
        'message' => 'kopi di bawah Rp30000',
        'assert' => static function (array $entities, array $result): array {
            return [
                expectEqual('intent', (string)($result['intent'] ?? ''), 'tanya_harga'),
                expectEqual('budget operator', (string)($entities['budget']['operator'] ?? ''), 'lte'),
                expectEqual('budget amount', (int)($entities['budget']['amount'] ?? 0), 30000),
                expectContains('reply budget phrase', (string)($result['reply_message'] ?? ''), 'Rp30.000'),
            ];
        },
    ],
    [
        'name' => 'variant_order',
        'message' => 'pesan 2 latte large',
        'assert' => static function (array $entities, array $result): array {
            return [
                expectEqual('intent', (string)($result['intent'] ?? ''), 'tambah_item'),
                expectEqual('qty', (int)($entities['products'][0]['qty'] ?? 0), 2),
                expectEqual('variant', (string)($entities['products'][0]['variant_label'] ?? ''), 'large'),
                expectContains('reply variant name', (string)($result['reply_message'] ?? ''), 'Latte - Large'),
            ];
        },
    ],
    [
        'name' => 'price_hint_order',
        'message' => 'pesan americano harga Rp20000',
        'assert' => static function (array $entities, array $result): array {
            return [
                expectEqual('intent', (string)($result['intent'] ?? ''), 'tambah_item'),
                expectEqual('entity product', (string)($entities['products'][0]['name_candidate'] ?? ''), 'americano'),
                expectEqual('mentioned price', (int)($entities['products'][0]['mentioned_price'] ?? 0), 20000),
                expectContains('reply asks size', (string)($result['reply_message'] ?? ''), 'Ukuran untuk *Americano*'),
            ];
        },
    ],
    [
        'name' => 'promo_lookup',
        'message' => 'promo apa yang ada',
        'assert' => static function (array $entities, array $result): array {
            return [
                expectEqual('intent', (string)($result['intent'] ?? ''), 'tanya_promo'),
                expectEqual('no product entities', count((array)($entities['products'] ?? [])), 0),
                expectTrue('reply mentions promo', containsOneOf((string)($result['reply_message'] ?? ''), ['Promo', 'promo', 'Diskon', 'diskon'])),
            ];
        },
    ],
];

$failures = [];

foreach ($cases as $case) {
    $message = (string)$case['message'];
    $entities = $extractor->extract($message, 'IDR');
    $session = 'chat-regression-' . $case['name'] . '-' . bin2hex(random_bytes(4));
    $result = $service->process('web', $branchId, $session, $message);
    $checks = $case['assert']($entities, $result);

    foreach ($checks as $check) {
        if ($check['ok'] !== true) {
            $failures[] = '[' . $case['name'] . '] ' . $check['message'];
        }
    }

    if ($verbose) {
        echo '--- ' . $case['name'] . " ---\n";
        echo 'message: ' . $message . "\n";
        echo 'intent: ' . (string)($result['intent'] ?? '-') . "\n";
        echo 'entities: ' . json_encode($entities, JSON_UNESCAPED_UNICODE) . "\n";
        echo 'reply: ' . squashWhitespace((string)($result['reply_message'] ?? '')) . "\n\n";
    }
}

if (!empty($failures)) {
    echo "FAIL\n";
    foreach ($failures as $failure) {
        echo '- ' . $failure . "\n";
    }
    exit(1);
}

echo "PASS\n";
echo 'Branch: ' . $branchId . "\n";
echo 'Cases: ' . count($cases) . "\n";
exit(0);

function findDefaultBranchId(): int
{
    $row = Database::getInstance()->query(
        'SELECT id FROM branches WHERE is_active = 1 ORDER BY id LIMIT 1'
    )->fetch();

    return (int)($row['id'] ?? 0);
}

function expectEqual(string $label, mixed $actual, mixed $expected): array
{
    return [
        'ok' => $actual === $expected,
        'message' => $label . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true),
    ];
}

function expectContains(string $label, string $haystack, string $needle): array
{
    return [
        'ok' => str_contains($haystack, $needle),
        'message' => $label . ' expected to contain ' . var_export($needle, true) . ', got ' . var_export(squashWhitespace($haystack), true),
    ];
}

function expectTrue(string $label, bool $value): array
{
    return [
        'ok' => $value === true,
        'message' => $label . ' expected true, got false',
    ];
}

function containsOneOf(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, (string)$needle)) {
            return true;
        }
    }

    return false;
}

function squashWhitespace(string $text): string
{
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}
