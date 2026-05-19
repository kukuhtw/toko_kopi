<?php

declare(strict_types=1);

require_once __DIR__ . '/InstagramDmChannel.php';
require_once __DIR__ . '/InstagramDmPlugin.php';

return [
    'class'       => InstagramDmPlugin::class,
    'name'        => 'Instagram DM Chatbot',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Integrasi chatbot untuk Instagram Direct Message via Meta Graph API.',
    'requires'    => '1.0.0',
];
