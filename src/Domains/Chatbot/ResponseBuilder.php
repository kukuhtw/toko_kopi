<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

class ResponseBuilder
{
    public function text(string $message, array $meta = []): array
    {
        return array_merge([
            'success' => true,
            'type' => 'text',
            'message' => $message,
        ], $meta);
    }

    public function data(string $message, array $data, array $meta = []): array
    {
        return array_merge([
            'success' => true,
            'type' => 'data',
            'message' => $message,
            'data' => $data,
        ], $meta);
    }
}
