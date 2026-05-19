<?php

declare(strict_types=1);

require_once __DIR__ . '/DiscordChannel.php';
require_once __DIR__ . '/DiscordChannelPlugin.php';

return [
    'class'       => DiscordChannelPlugin::class,
    'name'        => 'Discord Channel',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Plugin channel Discord yang mendukung bot per cabang maupun satu bot host untuk semua cabang.',
    'requires'    => '1.0.0',
];
