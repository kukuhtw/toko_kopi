<?php

declare(strict_types=1);

namespace App\Services;

final class TiktokShopApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string)(getenv('TIKTOKSHOP_BASE_URL') ?: 'https://open-api.tiktokglobalshop.com'), '/');
    }

    public function appKey(): string
    {
        return (string)getenv('TIKTOKSHOP_APP_KEY');
    }

    public function redirectUrl(): string
    {
        return (string)getenv('TIKTOKSHOP_REDIRECT_URL');
    }

    public function generateAuthUrl(): string
    {
        return $this->baseUrl()
            . '/authorization?app_key=' . urlencode($this->appKey())
            . '&redirect_uri=' . urlencode($this->redirectUrl());
    }
}
