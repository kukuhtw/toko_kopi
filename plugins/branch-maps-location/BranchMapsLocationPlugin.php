<?php

declare(strict_types=1);

final class BranchMapsLocationPlugin
{
    public function getName(): string
    {
        return 'Branch Maps Location';
    }

    public function getCode(): string
    {
        return 'branch-maps-location';
    }

    public function getDescription(): string
    {
        return 'Manage Google Maps locations for business branches.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFeatures(): array
    {
        return [
            'branch-location',
            'google-maps',
            'latitude-longitude',
            'embed-url',
            'multi-branch',
        ];
    }
}
