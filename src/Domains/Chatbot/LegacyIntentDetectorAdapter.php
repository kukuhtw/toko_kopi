<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

use App\Services\IntentDetector;
use KopiBot\Contracts\IntentDetectorInterface;

final class LegacyIntentDetectorAdapter implements IntentDetectorInterface
{
    private IntentDetector $detector;

    public function __construct(?IntentDetector $detector = null)
    {
        $this->detector = $detector ?? new IntentDetector();
    }

    public function detect(string $message, array $context = []): string
    {
        return $this->detector->detect($message, $context);
    }

    public function detectAll(string $message, array $context = []): array
    {
        return $this->detector->detectAll($message, $context);
    }

    public function extractOrderIntent(string $message): array
    {
        return $this->detector->extractOrderIntent($message);
    }
}
