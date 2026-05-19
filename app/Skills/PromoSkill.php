<?php

declare(strict_types=1);

namespace App\Skills;

use App\Helpers\Currency;
use App\Models\PromoModel;
use App\Services\PromoRagResponder;

class PromoSkill implements SkillInterface
{
    private PromoModel $promoModel;
    private PromoRagResponder $ragResponder;

    public function __construct()
    {
        $this->promoModel = new PromoModel();
        $this->ragResponder = new PromoRagResponder();
    }

    public function canHandle(string $intent): bool
    {
        return $intent === 'tanya_promo';
    }

    public function handle(array $ctx): array
    {
        $branchId = $ctx['branch_id'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $promos   = $this->promoModel->getActiveForBranch($branchId, $ctx['now_local'] ?? '');

        if (empty($promos)) {
            $reply = $lang === 'id'
                ? 'Saat ini belum ada promo aktif. Pantau terus ya!'
                : 'No active promos at the moment. Stay tuned!';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => [], 'conv_context' => ['last_topic' => 'promo', 'last_promos' => []]];
        }

        $resolved = $this->ragResponder->resolvePromos($ctx['message'] ?? '', $branchId, $ctx['now_local'] ?? '', 4);
        if ($this->ragResponder->isEnabled() && !empty($resolved)) {
            $ragReply = $this->ragResponder->composeReply($ctx, $resolved);
            if ($ragReply !== null) {
                return [
                    'reply' => trim($ragReply),
                    'state' => 'idle',
                    'action_result' => $resolved,
                    'conv_context' => [
                        'last_topic' => 'promo',
                        'last_promos' => array_map(static fn(array $promo): array => [
                            'id' => (int)($promo['id'] ?? 0),
                            'title' => (string)($promo['title'] ?? ''),
                            'promo_code' => (string)($promo['promo_code'] ?? ''),
                        ], $resolved),
                    ],
                ];
            }
        }

        $header = $lang === 'id' ? "Promo aktif saat ini:\n" : "Current active promos:\n";
        $lines  = [$header];

        foreach ($promos as $promo) {
            $discount = $promo['discount_type'] === 'percent'
                ? "{$promo['discount_value']}%"
                : Currency::format((float)$promo['discount_value'], $currency);

            $lines[] = "🎉 *{$promo['title']}*";
            $lines[] = "   {$promo['description']}";
            $lines[] = "   Diskon: {$discount}";
            if (!empty($promo['promo_code'])) {
                $lines[] = "   Kode: `{$promo['promo_code']}`";
            }
            $lines[] = '';
        }

        $footer = $lang === 'id'
            ? "Gunakan kode promo saat checkout. Ketik _checkout_ untuk mulai."
            : "Use promo code at checkout. Type _checkout_ to start.";
        $lines[] = $footer;

        return [
            'reply'         => implode("\n", $lines),
            'state'         => 'idle',
            'action_result' => $promos,
            'conv_context'  => [
                'last_topic' => 'promo',
                'last_promos' => array_map(static fn(array $promo): array => [
                    'id' => (int)($promo['id'] ?? 0),
                    'title' => (string)($promo['title'] ?? ''),
                    'promo_code' => (string)($promo['promo_code'] ?? ''),
                ], array_slice($promos, 0, 5)),
            ],
        ];
    }
}
