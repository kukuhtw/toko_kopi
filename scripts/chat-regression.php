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
    [
        'name' => 'variant_followup_small',
        'steps' => [
            'pesan 1 latte',
            'kecil',
        ],
        'assert_steps' => static function (array $history): array {
            $first = $history[0] ?? ['entities' => [], 'result' => []];
            $second = $history[1] ?? ['entities' => [], 'result' => []];

            return [
                expectEqual('step1 intent', (string)($first['result']['intent'] ?? ''), 'tambah_item'),
                expectContains('step1 asks size', (string)($first['result']['reply_message'] ?? ''), 'Ukuran untuk *Latte*'),
                expectEqual('step2 intent', (string)($second['result']['intent'] ?? ''), 'tambah_item'),
                expectContains('step2 adds latte small', (string)($second['result']['reply_message'] ?? ''), 'Latte - Small'),
                expectNotContains('step2 not wrong item', (string)($second['result']['reply_message'] ?? ''), 'Gibraltar'),
            ];
        },
    ],
    [
        'name' => 'checkout_email_skip',
        'steps' => [
            'pesan 1 americano small',
            '-',
            'checkout',
            'Regression User',
            'skip',
        ],
        'assert_steps' => static function (array $history): array {
            $checkoutPrompt = $history[2] ?? ['entities' => [], 'result' => []];
            $nameReply = $history[3] ?? ['entities' => [], 'result' => []];
            $skipReply = $history[4] ?? ['entities' => [], 'result' => []];

            return [
                expectContains('checkout asks name', (string)($checkoutPrompt['result']['reply_message'] ?? ''), 'nama'),
                expectEqual('name step intent', (string)($nameReply['result']['intent'] ?? ''), 'isi_nama'),
                expectContains('name step asks email', (string)($nameReply['result']['reply_message'] ?? ''), 'email'),
                expectEqual('skip step intent', (string)($skipReply['result']['intent'] ?? ''), 'isi_email'),
                expectNotContains('skip step does not repeat email', (string)($skipReply['result']['reply_message'] ?? ''), 'type *skip* to skip'),
                expectTrue(
                    'skip step advances to wa/address/postal/summary',
                    containsOneOf((string)($skipReply['result']['reply_message'] ?? ''), ['WhatsApp', 'Alamat', 'Address', 'Kode pos', 'Postal', 'Ringkasan Order', 'Order Summary'])
                ),
            ];
        },
    ],
];

$failures = [];

foreach ($cases as $case) {
    $session = 'chat-regression-' . $case['name'] . '-' . bin2hex(random_bytes(4));
    $history = [];

    if (isset($case['steps']) && is_array($case['steps'])) {
        foreach ($case['steps'] as $message) {
            $message = (string)$message;
            $entities = $extractor->extract($message, 'IDR');
            $result = $service->process('web', $branchId, $session, $message);
            $history[] = [
                'message' => $message,
                'entities' => $entities,
                'result' => $result,
            ];
        }
        $checks = $case['assert_steps']($history);
    } else {
        $message = (string)$case['message'];
        $entities = $extractor->extract($message, 'IDR');
        $result = $service->process('web', $branchId, $session, $message);
        $history[] = [
            'message' => $message,
            'entities' => $entities,
            'result' => $result,
        ];
        $checks = $case['assert']($entities, $result);
    }

    foreach ($checks as $check) {
        if ($check['ok'] !== true) {
            $failures[] = '[' . $case['name'] . '] ' . $check['message'];
        }
    }

    if ($verbose) {
        echo '--- ' . $case['name'] . " ---\n";
        foreach ($history as $index => $step) {
            echo 'step ' . ($index + 1) . ' message: ' . $step['message'] . "\n";
            echo 'step ' . ($index + 1) . ' intent: ' . (string)($step['result']['intent'] ?? '-') . "\n";
            echo 'step ' . ($index + 1) . ' entities: ' . json_encode($step['entities'], JSON_UNESCAPED_UNICODE) . "\n";
            echo 'step ' . ($index + 1) . ' reply: ' . squashWhitespace((string)($step['result']['reply_message'] ?? '')) . "\n";
        }
        echo "\n";
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

function expectNotContains(string $label, string $haystack, string $needle): array
{
    return [
        'ok' => !str_contains($haystack, $needle),
        'message' => $label . ' expected not to contain ' . var_export($needle, true) . ', got ' . var_export(squashWhitespace($haystack), true),
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
