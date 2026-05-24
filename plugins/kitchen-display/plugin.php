<?php

require_once __DIR__ . '/KitchenDisplayPlugin.php';

return [
    'class'       => KitchenDisplayPlugin::class,
    'name'        => 'Kitchen Display',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Tampilan dapur real-time (KDS) — papan Kanban untuk staf melihat dan memproses order masuk, dengan auto-refresh dan notifikasi suara.',
    'requires'    => '1.0.0',
];
