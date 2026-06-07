<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface IntentDetectorInterface
{
    public function detect(string $message, array $context = []): string;

    /**
     * Return all matching intents sorted by relevance.
     *
     * @return list<string>
     */
    public function detectAll(string $message, array $context = []): array;

    /**
     * @return array{item_query:string, qty:int}
     */
    public function extractOrderIntent(string $message): array;
}
