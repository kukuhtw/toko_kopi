<?php

declare(strict_types=1);

namespace KopiBot\Skills;

final class SkillRegistry
{
    public static function register(array $skills, SkillInterface $skill, int $priority = 100): array
    {
        $skills[] = [
            'skill' => $skill,
            'priority' => $priority,
        ];

        return $skills;
    }
}
