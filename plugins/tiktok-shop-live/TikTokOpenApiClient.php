<?php

declare(strict_types=1);

final class TikTokOpenApiClient
{
    public function __construct(
        private string $appKey,
        private string $appSecret,
        private string $accessToken = ''
    ) {}

    public function getAuthorizationHeader(): array
    {
        if ($this->accessToken === '') {
            return [];
        }

        return [
            'Authorization: Bearer ' . $this->accessToken,
        ];
    }

    public function getShopInfo(): array
    {
        return [
            'connected' => $this->accessToken !== '',
            'app_key' => $this->appKey,
        ];
    }
}
