<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Memory\MemoryStoreInterface;
use App\Agent\Planner\CustomerPlanner;
use App\Agent\Policy\PolicyEngine;
use App\Agent\Routing\ConversationModeRouter;
use App\Agent\Tools\AddToCartTool;
use App\Agent\Tools\ApplyPromoTool;
use App\Agent\Tools\BeginCheckoutTool;
use App\Agent\Tools\GetActivePromosTool;
use App\Agent\Tools\GetBranchMenuTool;
use App\Agent\Tools\GetBranchInfoTool;
use App\Agent\Tools\GetCartSnapshotTool;
use App\Agent\Tools\RecommendMenuTool;

final class CustomerAgentKernel
{
    private ConversationModeRouter $router;
    private ToolRegistry $toolRegistry;
    private PolicyEngine $policyEngine;
    private CustomerPlanner $planner;
    private MemoryStoreInterface $memoryStore;

    public function __construct(
        MemoryStoreInterface $memoryStore,
        ?ConversationModeRouter $router = null,
        ?ToolRegistry $toolRegistry = null,
        ?PolicyEngine $policyEngine = null,
        ?CustomerPlanner $planner = null
    ) {
        $this->memoryStore = $memoryStore;
        $this->router = $router ?? new ConversationModeRouter();
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry();
        $this->policyEngine = $policyEngine ?? new PolicyEngine();
        $this->planner = $planner ?? new CustomerPlanner();

        $this->registerDefaultTools();
    }

    /**
     * Customer-facing agent entrypoint.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function handle(array $context): array
    {
        $mode = $this->router->route($context);
        if ($mode === 'transactional') {
            return [
                'mode' => 'transactional',
                'reply' => '',
                'tool_calls' => [],
                'handoff' => 'chatbot_engine',
            ];
        }

        $customerKey = $this->buildCustomerKey($context);
        $memories = $this->memoryStore->getMemories('customer', $customerKey, 5);
        $plan = $this->planner->buildPlan($context, $this->toolRegistry->describeAll());

        $toolCalls = [];
        $observations = [];

        foreach ($plan as $step) {
            $tool = $this->toolRegistry->get((string)($step['tool'] ?? ''));
            if (!$tool) {
                continue;
            }

            $input = (array)($step['input'] ?? []);
            $decision = $this->policyEngine->evaluate($tool, $context, $input);
            if (!$decision->allowed) {
                return [
                    'mode' => 'advisory',
                    'reply' => $decision->reason,
                    'tool_calls' => $toolCalls,
                    'handoff' => $decision->resolution,
                    'memory_hits' => $memories,
                ];
            }

            $output = $tool->execute($input, $context);
            $toolCalls[] = [
                'tool' => $tool->getName(),
                'input' => $input,
                'output' => $output,
            ];
            $observations[$tool->getName()] = $output;
        }

        $reply = $this->composeReply($context, $observations, $memories);
        $this->memoryStore->remember(
            'customer',
            $customerKey,
            'conversation_summary',
            $reply,
            ['intent' => (string)($context['intent'] ?? '')]
        );
        $this->reflectSuccessfulInteraction($customerKey, $context, $observations, $toolCalls, $reply);

        return [
            'mode' => 'advisory',
            'reply' => $reply,
            'tool_calls' => $toolCalls,
            'handoff' => null,
            'memory_hits' => $memories,
        ];
    }

    private function registerDefaultTools(): void
    {
        $this->toolRegistry->register(new GetBranchMenuTool());
        $this->toolRegistry->register(new GetBranchInfoTool());
        $this->toolRegistry->register(new GetCartSnapshotTool());
        $this->toolRegistry->register(new GetActivePromosTool());
        $this->toolRegistry->register(new AddToCartTool());
        $this->toolRegistry->register(new ApplyPromoTool());
        $this->toolRegistry->register(new BeginCheckoutTool());
        $this->toolRegistry->register(new RecommendMenuTool());
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $observations
     * @param array<int, array<string, mixed>> $memories
     */
    private function composeReply(array $context, array $observations, array $memories): string
    {
        $intent = (string)($context['intent'] ?? '');

        if ($intent === 'lihat_cart' && isset($observations['get_cart_snapshot'])) {
            $cart = $observations['get_cart_snapshot'];
            $items = (array)($cart['items'] ?? []);
            if (empty($items)) {
                return 'Keranjang kamu masih kosong. Kalau mau, saya bisa bantu rekomendasikan menu yang cocok.';
            }

            $lines = ["Pesanan kamu saat ini:"];
            foreach (array_slice($items, 0, 5) as $item) {
                $lines[] = '- ' . ($item['name'] ?? '-') . ' x' . (int)($item['quantity'] ?? 0);
            }
            $lines[] = 'Kalau mau, saya bisa bantu cek promo yang cocok atau lanjut ke checkout.';
            return implode("\n", $lines);
        }

        if ($intent === 'tanya_promo' && isset($observations['get_active_promos'])) {
            $promos = (array)($observations['get_active_promos']['promos'] ?? []);
            if (empty($promos)) {
                return 'Saat ini belum ada promo aktif yang bisa saya tampilkan untuk cabang ini.';
            }

            $top = array_slice($promos, 0, 3);
            $lines = ['Promo aktif yang paling relevan saat ini:'];
            foreach ($top as $promo) {
                $lines[] = '- ' . ($promo['title'] ?? 'Promo') . ': ' . ($promo['description'] ?? 'Lihat syarat promo di cabang ini.');
            }
            return implode("\n", $lines);
        }

        if (isset($observations['apply_promo'])) {
            $promoResult = (array)$observations['apply_promo'];
            if (!empty($promoResult['message'])) {
                return (string)$promoResult['message'];
            }

            if (($promoResult['status'] ?? '') === 'applied') {
                $promoCode = (string)($promoResult['promo_code'] ?? '');
                $discountFormatted = (string)($promoResult['discount_formatted'] ?? '');
                $reply = 'Kode promo ' . $promoCode . ' berhasil dipakai.';
                if ($discountFormatted !== '') {
                    $reply .= ' Diskon ' . $discountFormatted . ' sudah diterapkan.';
                }
                $reply .= "\nKalau mau, saya bisa bantu cek isi keranjang kamu juga.";
                return $reply;
            }
        }

        if (isset($observations['add_to_cart'])) {
            $addResult = (array)$observations['add_to_cart'];
            if (!empty($addResult['message'])) {
                return (string)$addResult['message'];
            }

            if (($addResult['status'] ?? '') === 'added') {
                $item = (array)($addResult['item'] ?? []);
                $variant = is_array($addResult['variant'] ?? null) ? (array)$addResult['variant'] : [];
                $qty = (int)($addResult['qty'] ?? 1);
                $displayName = (string)($item['name'] ?? 'Item');
                if (!empty($variant['label'])) {
                    $displayName .= ' - ' . $variant['label'];
                }

                $reply = $qty > 1
                    ? $qty . ' x ' . $displayName . ' sudah saya tambahkan ke keranjang.'
                    : $displayName . ' sudah saya tambahkan ke keranjang.';

                if (!empty($addResult['resolved_from_history'])) {
                    $reply .= ' Saya pilihkan berdasarkan favorit atau pesanan kamu sebelumnya.';
                }

                if (!empty($addResult['line_total_formatted'])) {
                    $reply .= ' Estimasi subtotal item ini ' . (string)$addResult['line_total_formatted'] . '.';
                }

                $reply .= "\nKalau mau, saya bisa bantu cek keranjang atau lanjut pilih item lain.";
                return $reply;
            }
        }

        if (isset($observations['get_branch_info'])) {
            $branchInfo = (array)$observations['get_branch_info'];
            $branch = (array)($branchInfo['branch'] ?? []);
            $settings = (array)($branchInfo['settings'] ?? []);
            if (!empty($branch)) {
                $lang = (string)($context['language'] ?? 'id');
                $lines = [];
                $branchName = (string)($branch['name'] ?? 'Cabang kami');

                if ($lang === 'en') {
                    $lines[] = 'Here is the information for *' . $branchName . '*:';
                    if (!empty($settings['description_en'])) {
                        $lines[] = $settings['description_en'];
                    }
                    if (!empty($branch['address'])) {
                        $lines[] = 'Address: ' . $branch['address'];
                    }
                    if (!empty($branch['city'])) {
                        $lines[] = 'City: ' . $branch['city'];
                    }
                    if (!empty($branch['phone'])) {
                        $lines[] = 'Phone: ' . $branch['phone'];
                    }
                    if (!empty($branch['email'])) {
                        $lines[] = 'Email: ' . $branch['email'];
                    }
                    if (!empty($settings['hours_en'])) {
                        $lines[] = 'Operating hours: ' . $settings['hours_en'];
                    }
                    $lines[] = 'If you want, I can also help recommend drinks or show active promos.';
                } else {
                    $lines[] = 'Berikut info *' . $branchName . '*:';
                    if (!empty($settings['description_id'])) {
                        $lines[] = $settings['description_id'];
                    }
                    if (!empty($branch['address'])) {
                        $lines[] = 'Alamat: ' . $branch['address'];
                    }
                    if (!empty($branch['city'])) {
                        $lines[] = 'Kota: ' . $branch['city'];
                    }
                    if (!empty($branch['phone'])) {
                        $lines[] = 'Telepon: ' . $branch['phone'];
                    }
                    if (!empty($branch['email'])) {
                        $lines[] = 'Email: ' . $branch['email'];
                    }
                    if (!empty($settings['hours_id'])) {
                        $lines[] = 'Jam operasional: ' . $settings['hours_id'];
                    }
                    $lines[] = 'Kalau mau, saya juga bisa bantu rekomendasikan menu atau cek promo aktif.';
                }

                return implode("\n", array_filter($lines, static fn(string $line): bool => trim($line) !== ''));
            }
        }

        if (isset($observations['recommend_menu'])) {
            $recommendation = (array)$observations['recommend_menu'];
            $items = (array)($recommendation['recommendations'] ?? []);
            if (!empty($items)) {
                $budget = $recommendation['budget'] ?? null;
                $preferences = (array)($recommendation['preferences'] ?? []);
                $customerSignals = (array)($recommendation['customer_signals'] ?? []);
                $intro = 'Aku rekomendasikan menu ini buat kamu:';
                if (is_numeric($budget) && (float)$budget > 0) {
                    $intro = 'Kalau budget kamu sekitar Rp' . number_format((float)$budget, 0, ',', '.') . ', ini yang paling cocok:';
                } elseif (!empty($customerSignals['history_request']) && !empty($customerSignals['has_customer_history'])) {
                    $intro = 'Kalau mau yang mirip favorit atau pesanan kamu sebelumnya, ini yang paling nyambung:';
                } elseif (!empty($customerSignals['has_customer_history'])) {
                    $intro = 'Aku pilihkan yang cocok, sambil mempertimbangkan favorit dan pesanan kamu sebelumnya:';
                }

                $lines = [$intro];
                foreach ($items as $item) {
                    $price = (float)($item['effective_price'] ?? $item['price'] ?? 0);
                    $lines[] = '- ' . ($item['name'] ?? '-') . ' - Rp' . number_format($price, 0, ',', '.');
                }

                if (!empty($preferences['flavor']) || !empty($preferences['temperature'])) {
                    $lines[] = 'Kalau mau, saya bisa bantu pilihkan yang lebih manis, lebih strong, panas, atau dingin juga.';
                } else {
                    $lines[] = 'Kalau kamu punya preferensi rasa atau mau versi panas/dingin, tinggal bilang saja.';
                }

                return implode("\n", $lines);
            }
        }

        $memoryHint = '';
        if (!empty($memories)) {
            $memoryHint = 'Saya juga ingat preferensi percakapan sebelumnya, jadi kalau kamu mau rekomendasi yang mirip pesanan lalu, saya bisa bantu.';
        }

        return trim('Saya bisa bantu jelaskan menu, bandingkan opsi, cek promo aktif, atau bantu pilih minuman yang paling cocok. ' . $memoryHint);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildCustomerKey(array $context): string
    {
        $channel = (string)($context['channel'] ?? 'web');
        $branchId = (int)($context['branch_id'] ?? 0);
        $customerId = (int)($context['customer']['id'] ?? 0);

        return $channel . ':' . $branchId . ':' . $customerId;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $observations
     * @param array<int, array<string, mixed>> $toolCalls
     */
    private function reflectSuccessfulInteraction(
        string $customerKey,
        array $context,
        array $observations,
        array $toolCalls,
        string $reply
    ): void {
        $message = trim((string)($context['message'] ?? ''));
        $intent = (string)($context['intent'] ?? '');
        $toolNames = array_values(array_filter(array_map(
            static fn(array $call): string => (string)($call['tool'] ?? ''),
            $toolCalls
        )));

        if ($message !== '' && $reply !== '') {
            $summary = 'Customer asked: ' . $message . ' | Agent reply: ' . mb_substr($reply, 0, 240, 'UTF-8');
            $this->memoryStore->remember(
                'customer',
                $customerKey,
                'successful_advisory',
                $summary,
                [
                    'intent' => $intent,
                    'tools' => $toolNames,
                ]
            );
        }

        if (isset($observations['recommend_menu'])) {
            $recommendation = (array)$observations['recommend_menu'];
            $preferences = (array)($recommendation['preferences'] ?? []);
            $budget = $recommendation['budget'] ?? null;
            $customerSignals = (array)($recommendation['customer_signals'] ?? []);

            $parts = [];
            if (is_numeric($budget) && (float)$budget > 0) {
                $parts[] = 'budget=' . (string)(int)$budget;
            }
            if (!empty($preferences['flavor'])) {
                $parts[] = 'flavor=' . (string)$preferences['flavor'];
            }
            if (!empty($preferences['temperature'])) {
                $parts[] = 'temperature=' . (string)$preferences['temperature'];
            }
            if (!empty($customerSignals['history_request'])) {
                $parts[] = 'history_request=yes';
            }

            if (!empty($parts)) {
                $this->memoryStore->remember(
                    'customer',
                    $customerKey,
                    'customer_preference',
                    implode('; ', $parts),
                    [
                        'intent' => $intent,
                        'source_tool' => 'recommend_menu',
                    ]
                );
            }

            $phraseSignature = $this->buildRecommendationPhraseSignature($message, $preferences, $budget, $customerSignals);
            if ($phraseSignature !== '') {
                $this->memoryStore->remember(
                    'customer',
                    $customerKey,
                    'recommendation_phrase',
                    $phraseSignature,
                    [
                        'original_message' => $message,
                        'intent' => $intent,
                    ]
                );
            }
        }

        if (isset($observations['add_to_cart'])) {
            $addResult = (array)$observations['add_to_cart'];
            if (($addResult['status'] ?? '') === 'added') {
                $item = (array)($addResult['item'] ?? []);
                $variant = is_array($addResult['variant'] ?? null) ? (array)$addResult['variant'] : [];
                $label = (string)($item['name'] ?? '');
                if (!empty($variant['label'])) {
                    $label .= ' - ' . $variant['label'];
                }

                if ($label !== '') {
                    $this->memoryStore->remember(
                        'customer',
                        $customerKey,
                        'customer_preference',
                        'repeat_item=' . $label,
                        [
                            'intent' => $intent,
                            'source_tool' => 'add_to_cart',
                            'resolved_from_history' => !empty($addResult['resolved_from_history']),
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param array<string, string> $preferences
     * @param array<string, mixed> $customerSignals
     */
    private function buildRecommendationPhraseSignature(
        string $message,
        array $preferences,
        mixed $budget,
        array $customerSignals
    ): string {
        $tags = [];
        if (is_numeric($budget) && (float)$budget > 0) {
            $tags[] = 'budget';
        }
        if (!empty($preferences['flavor'])) {
            $tags[] = 'flavor:' . $preferences['flavor'];
        }
        if (!empty($preferences['temperature'])) {
            $tags[] = 'temperature:' . $preferences['temperature'];
        }
        if (!empty($customerSignals['history_request'])) {
            $tags[] = 'history';
        }

        if (!empty($tags)) {
            return implode(' | ', $tags);
        }

        $normalized = mb_strtolower(trim($message), 'UTF-8');
        $normalized = preg_replace('/\d+\s*(rb|ribu|k)?/u', '{budget}', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        return mb_substr($normalized, 0, 120, 'UTF-8');
    }
}
