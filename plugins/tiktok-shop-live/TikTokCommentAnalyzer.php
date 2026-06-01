<?php

declare(strict_types=1);

final class TikTokCommentAnalyzer
{
    public function analyze(array $comments): array
    {
        $keywords = [
            'harga' => 0,
            'promo' => 0,
            'ongkir' => 0,
            'stok' => 0,
            'ready' => 0,
            'halal' => 0,
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
            'top_question' => array_key_first($keywords),
        ];
    }
}
