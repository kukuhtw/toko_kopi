<?php

declare(strict_types=1);

namespace App\Services;

interface IntentDetectorInterface
{
    public function detect(string $message, array $context = []): string;

    /** Return all matching intents sorted by relevance (highest first). */
    public function detectAll(string $message, array $context = []): array;

    public function extractOrderIntent(string $message): array;
}
