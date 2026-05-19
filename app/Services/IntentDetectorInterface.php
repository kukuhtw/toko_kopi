<?php

declare(strict_types=1);

namespace App\Services;

interface IntentDetectorInterface
{
    public function detect(string $message, array $context = []): string;
    public function extractOrderIntent(string $message): array;
}
