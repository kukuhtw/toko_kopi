<?php

declare(strict_types=1);

require_once __DIR__ . '/LoyaltyPointRepository.php';
require_once __DIR__ . '/LoyaltyPointSkill.php';
require_once __DIR__ . '/LoyaltyPointPlugin.php';

return [
    'class'       => LoyaltyPointPlugin::class,
    'name'        => 'Loyalty Point',
    'version'     => '1.0.0',
    'author'      => 'KopiBot',
    'description' => 'Program poin loyalitas per cabang: akumulasi poin saat order selesai, cek saldo via chatbot, dan ringkasan member di dashboard.',
    'requires'    => '1.0.0',
];
