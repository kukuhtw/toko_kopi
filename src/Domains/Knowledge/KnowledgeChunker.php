<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

class KnowledgeChunker
{
    public function chunk(string $text, int $maxChars = 1200, int $overlapChars = 150): array
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return [];
        }

        $maxChars = max(300, $maxChars);
        $overlapChars = max(0, min($overlapChars, (int)floor($maxChars / 2)));

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach ($this->splitLongText($paragraph, $maxChars, $overlapChars) as $part) {
                    $chunks[] = $part;
                }
                continue;
            }

            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }
            $current = $paragraph;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_values(array_filter(array_map('trim', $chunks)));
    }

    private function splitLongText(string $text, int $maxChars, int $overlapChars): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $part = mb_substr($text, $offset, $maxChars);
            $chunks[] = trim($part);

            $step = $maxChars - $overlapChars;
            if ($step <= 0) {
                break;
            }
            $offset += $step;
        }

        return $chunks;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
