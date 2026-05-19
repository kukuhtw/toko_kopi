<?php

require_once __DIR__ . '/NotifikasiAdminPlugin.php';

return [
    'class'       => NotifikasiAdminPlugin::class,
    'name'        => 'Notifikasi Admin',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Kirim notifikasi in-app dan email ke admin cabang setiap ada order baru.',
    'requires'    => '1.0.0',
];
