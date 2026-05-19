<?php

declare(strict_types=1);

namespace App\Skills;

final class SkillRegistry
{
    /**
     * Helper untuk mendaftarkan skill dengan prioritas eksplisit.
     * Semakin kecil angkanya, semakin dulu skill dijalankan.
     *
     * @param array<int, mixed> $skills
     */
    public static function register(array $skills, SkillInterface $skill, int $priority = 100): array
    {
        $skills[] = [
            'skill'     => $skill,
            'priority'  => $priority,
        ];

        return $skills;
    }
}
