<?php

declare(strict_types=1);

namespace App\Services;

class PharmacyWhatsappGatewayService
{
    public function sendMessage(string $provider, string $phone, string $message): array
    {
        return match (strtolower($provider)) {
            'fonnte' => $this->sendViaFonnte($phone, $message),
            'wablas' => $this->sendViaWablas($phone, $message),
            'twilio' => $this->sendViaTwilio($phone, $message),
            default => [
                'success' => false,
                'message' => 'Unsupported provider'
            ],
        };
    }

    private function sendViaFonnte(string $phone, string $message): array
    {
        return [
            'success' => true,
            'provider' => 'fonnte',
            'phone' => $phone,
            'message' => $message,
        ];
    }

    private function sendViaWablas(string $phone, string $message): array
    {
        return [
            'success' => true,
            'provider' => 'wablas',
            'phone' => $phone,
            'message' => $message,
        ];
    }

    private function sendViaTwilio(string $phone, string $message): array
    {
        return [
            'success' => true,
            'provider' => 'twilio',
            'phone' => $phone,
            'message' => $message,
        ];
    }
}
