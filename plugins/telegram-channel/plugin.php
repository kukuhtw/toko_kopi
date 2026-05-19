<?php

declare(strict_types=1);

require_once __DIR__ . '/TelegramChannel.php';
require_once __DIR__ . '/TelegramChannelPlugin.php';

return [
    'class'       => TelegramChannelPlugin::class,
    'name'        => 'Telegram Channel',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel Telegram yang memindahkan webhook dan konfigurasi Telegram keluar dari core.',
    'requires'    => '1.0.0',
];
