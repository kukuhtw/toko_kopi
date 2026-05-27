<?php

declare(strict_types=1);

final class PromoCmsBridgeService
{
    public function generateArticlePayload(array $promo): array
    {
        $title = 'Promo ' . ($promo['title'] ?? 'Special Offer');

        $summary = sprintf(
            'Nikmati promo %s dengan diskon menarik untuk periode terbatas.',
            $promo['promo_code'] ?? ''
        );

        $article = sprintf(
            "Promo terbaru sekarang tersedia. Gunakan kode promo %s untuk mendapatkan penawaran spesial. Promo berlaku mulai %s sampai %s dengan minimum transaksi tertentu sesuai syarat dan ketentuan.",
            $promo['promo_code'] ?? '-',
            $promo['start_date'] ?? '-',
            $promo['end_date'] ?? '-'
        );

        $bannerPrompt = sprintf(
            'Landscape banner modern minimarket ecommerce promo campaign, colorful discount design, promo code %s, shopping basket, clean UI UX, Indonesia retail style, 16:9',
            $promo['promo_code'] ?? ''
        );

        return [
            'title' => $title,
            'summary' => $summary,
            'content' => $article,
            'ai_banner_prompt' => $bannerPrompt,
            'is_homepage_featured' => 1,
        ];
    }
}
