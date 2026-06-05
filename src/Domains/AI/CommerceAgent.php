<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class CommerceAgent
{
    public function __construct(
        private IntentExtractorInterface $extractor = new RuleBasedCommerceExtractor()
    ) {}

    public function understand(string $message): array
    {
        return $this->extractor->extract($message)->toArray();
    }
}
