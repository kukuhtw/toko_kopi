<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

class CommerceIntentResult
{
    public function __construct(
        public string $intent,
        public array $items = [],
        public array $meta = []
    ) {}

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'items' => $this->items,
            'meta' => $this->meta,
        ];
    }
}
