<?php

declare(strict_types=1);

final class ShopeeCommentAnalyzer
{
    public function analyze(array $comments): array
    {
        $keywords = [
            'harga' => 0,
            'voucher' => 0,
            'ongkir' => 0,
            'stok' => 0,
            'cod' => 0,
            'promo' => 0,
        ];

        foreach ($comments as $comment) {
            $text = strtolower((string)$comment);
            foreach ($keywords as $keyword => $count) {
                if (str_contains($text, $keyword)) {
                    $keywords[$keyword]++;
                }
            }
        }

        arsort($keywords);

        return [
            'total_comments' => count($comments),
            'keyword_frequency' => $keywords,
            'top_topic' => array_key_first($keywords),
        ];
    }
}
