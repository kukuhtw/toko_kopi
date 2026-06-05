<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

interface IntentExtractorInterface
{
    public function extract(string $message): CommerceIntentResult;
}
