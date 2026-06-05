<?php

declare(strict_types=1);

namespace KopiBot\Domains\FAQ;

class FaqDTO
{
    public function __construct(
        public int $tenantId,
        public ?int $branchId,
        public string $category,
        public string $question,
        public string $answer,
        public string $tags = '',
        public bool $isActive = true
    ) {}
}
