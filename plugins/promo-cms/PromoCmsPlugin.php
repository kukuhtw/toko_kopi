<?php

declare(strict_types=1);

final class PromoCmsPlugin
{
    public function getName(): string
    {
        return 'Promo CMS';
    }

    public function getCode(): string
    {
        return 'promo-cms';
    }

    public function getDescription(): string
    {
        return 'Promo news and discount content management system plugin.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFeatures(): array
    {
        return [
            'promo-news',
            'discount-content',
            'frontend-promo-page',
            'homepage-promo-link',
            'draft-publish',
        ];
    }
}
