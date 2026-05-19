<?php

declare(strict_types=1);

namespace App\Skills;

class RefusalSkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return $intent === 'out_of_scope';
    }

    public function handle(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';

        $responses = [
            'id' => [
                "Maaf, saya hanya bisa membantu dengan pesanan kopi dan menu kami. 😊\n\nKetik *menu* untuk lihat menu, atau *promo* untuk info diskon.",
                "Pertanyaan itu di luar kemampuan saya. Saya adalah asisten pemesanan kopi. Ada yang bisa saya bantu terkait menu atau pesanan?",
                "Hmm, sepertinya itu bukan bidang saya. Saya spesialis kopi! ☕ Mau lihat menu atau ada pesanan yang bisa saya bantu?",
            ],
            'en' => [
                "Sorry, I can only help with coffee orders and our menu. 😊\n\nType *menu* to browse or *promo* for discounts.",
                "That's outside my expertise. I'm a coffee ordering assistant. Can I help you with our menu or your order?",
                "That's not my specialty! I'm a coffee expert ☕. Want to see our menu or check your order?",
            ],
        ];

        $pool  = $responses[$lang] ?? $responses['id'];
        $reply = $pool[array_rand($pool)];

        return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
    }
}
