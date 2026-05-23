<?php

declare(strict_types=1);

final class KiriminAjaDeliveryClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function getOverview(): array
    {
        return [
            'base_url' => rtrim($this->baseUrl, '/'),
            'has_api_key' => $this->apiKey !== '',
        ];
    }
}
