<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Contracts\SkillInterface;

class SkillRegistry
{
    public static function register(array $skills, SkillInterface $skill, int $priority = 100): array
    {
        $skills[] = [
            'priority' => $priority,
            'skill' => $skill,
        ];

        usort($skills, static fn(array $a, array $b): int => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        return $skills;
    }

    public static function all(): array
    {
        $skills = HookManager::applyFilters('skills.registered', []);
        $normalized = [];

        foreach ((array)$skills as $item) {
            if ($item instanceof SkillInterface) {
                $normalized[] = ['priority' => 100, 'skill' => $item];
                continue;
            }

            if (is_array($item) && ($item['skill'] ?? null) instanceof SkillInterface) {
                $normalized[] = [
                    'priority' => (int)($item['priority'] ?? 100),
                    'skill' => $item['skill'],
                ];
            }
        }

        usort($normalized, static fn(array $a, array $b): int => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        return array_column($normalized, 'skill');
    }

    public static function findForIntent(string $intent): ?SkillInterface
    {
        foreach (self::all() as $skill) {
            if ($skill->canHandle($intent)) {
                return $skill;
            }
        }

        return null;
    }
}
