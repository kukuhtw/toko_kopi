<?php

declare(strict_types=1);

final class FaqVectorService
{
    private const DIMENSION = 64;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $tokens = $this->tokenize($text);
        $vector = array_fill(0, self::DIMENSION, 0.0);

        foreach ($tokens as $token) {
            $weight = 1.0 + min(1.5, mb_strlen($token, 'UTF-8') / 10);
            $index = abs(crc32($token)) % self::DIMENSION;
            $vector[$index] += $weight;
        }

        for ($i = 0, $count = count($tokens) - 1; $i < $count; $i++) {
            $bigram = $tokens[$i] . '_' . $tokens[$i + 1];
            $index = abs(crc32($bigram)) % self::DIMENSION;
            $vector[$index] += 1.25;
        }

        return $this->normalizeVector($vector);
    }

    public function normalizeText(string $text): string
    {
        return implode(' ', $this->tokenize($text));
    }

    public function checksum(string $text): string
    {
        return sha1($this->normalizeText($text));
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    public function cosine(array $left, array $right): float
    {
        $max = min(count($left), count($right));
        if ($max === 0) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $max; $i++) {
            $sum += ((float)$left[$i]) * ((float)$right[$i]);
        }

        return max(0.0, min(1.0, $sum));
    }

    public function dimension(): int
    {
        return self::DIMENSION;
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '') {
            return [];
        }

        $stopwords = [
            'yang','dan','atau','di','ke','dari','untuk','dengan','pada','itu','ini','saya','aku','kami','kita',
            'ada','apa','apakah','bisa','boleh','tolong','dong','ya','nih','nya','kah','mau','ingin','berapa',
            'the','a','an','is','are','do','does','can','could','please','me','my','our','your','of','to','in','on',
        ];
        $stopmap = array_fill_keys($stopwords, true);

        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if (isset($stopmap[$part])) {
                continue;
            }
            if (mb_strlen($part, 'UTF-8') < 2) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    /**
     * @param array<int, float> $vector
     * @return array<int, float>
     */
    private function normalizeVector(array $vector): array
    {
        $sumSquares = 0.0;
        foreach ($vector as $value) {
            $sumSquares += $value * $value;
        }
        if ($sumSquares <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sumSquares);
        foreach ($vector as $index => $value) {
            $vector[$index] = $value / $norm;
        }

        return $vector;
    }
}
