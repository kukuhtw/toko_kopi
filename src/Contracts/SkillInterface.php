<?php

declare(strict_types=1);

namespace KopiBot\Contracts;

interface SkillInterface
{
    public function canHandle(string $intent): bool;

    public function handle(array $context): array;
}
