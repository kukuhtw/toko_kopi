<?php

declare(strict_types=1);

namespace App\Services;

final class ShopeeApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string)(getenv('SHOPEE_BASE_URL') ?: 'https://partner.shopeemobile.com'), '/');
    }

    public function partnerId(): string
    {
        return (string)getenv('SHOPEE_PARTNER_ID');
    }

    public function redirectUrl(): string
    {
        return (string)getenv('SHOPEE_REDIRECT_URL');
    }

    public function generateAuthUrl(): string
    {
        $timestamp = time();

        return $this->baseUrl()
            . '/api/v2/shop/auth_partner?partner_id=' . urlencode($this->partnerId())
            . '&timestamp=' . $timestamp
            . '&redirect=' . urlencode($this->redirectUrl());
    }
}
