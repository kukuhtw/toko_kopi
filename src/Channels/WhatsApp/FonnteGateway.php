<?php

declare(strict_types=1);

namespace KopiBot\Channels\WhatsApp;

use GuzzleHttp\Client;
use KopiBot\Core\Config;

class FonnteGateway implements WhatsAppGatewayInterface
{
    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?: new Client([
            'timeout' => 15,
        ]);
    }

    public function sendMessage(OutgoingMessageDTO $message): array
    {
        $token = (string) Config::get('FONNTE_TOKEN', '');
        $url = (string) Config::get('FONNTE_SEND_URL', 'https://api.fonnte.com/send');

        if ($token === '') {
            return [
                'success' => false,
                'message' => 'FONNTE_TOKEN is empty',
            ];
        }

        $response = $this->http->post($url, [
            'headers' => [
                'Authorization' => $token,
            ],
            'form_params' => [
                'target' => $message->phone,
                'message' => $message->message,
            ],
        ]);

        return [
            'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
            'status_code' => $response->getStatusCode(),
            'response' => (string) $response->getBody(),
        ];
    }
}
