<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\HookManager;
use KopiBot\Core\SkillRegistry;
use KopiBot\Intent\IntentPatternRegistry;

class ExampleFaqSkillPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'Example Skill Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Toko Kopi';
    }

    public function register(): void
    {
        HookManager::addFilter('skills.registered', [$this, 'registerSkill']);
        HookManager::addFilter('intent.patterns', [$this, 'registerIntentPatterns']);
    }

    public function registerSkill(array $skills): array
    {
        return SkillRegistry::register($skills, new ExampleFaqSkill(), 70);
    }

    public function registerIntentPatterns(array $patterns): array
    {
        return IntentPatternRegistry::extend($patterns, 'faq_wifi', [
            'wifi',
            'wi-fi',
            'password wifi',
            'password wi-fi',
            'ada wifi',
            'wifi password',
            'internet',
        ]);
    }
}
