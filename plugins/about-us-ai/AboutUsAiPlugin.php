<?php

declare(strict_types=1);

final class AboutUsAiPlugin
{
    public function getName(): string
    {
        return 'About Us AI';
    }

    public function getCode(): string
    {
        return 'about-us-ai';
    }

    public function getDescription(): string
    {
        return 'AI-powered About Us content generator and content management plugin.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFeatures(): array
    {
        return [
            'manual-content',
            'llm-generation',
            'draft-publish',
            'business-profile',
            'seo-content',
        ];
    }
}
