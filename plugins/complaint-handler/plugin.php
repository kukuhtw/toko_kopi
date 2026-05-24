<?php

declare(strict_types=1);

require_once __DIR__ . '/ComplaintTicketRepository.php';
require_once __DIR__ . '/ComplaintAnalyzer.php';
require_once __DIR__ . '/ComplaintSkill.php';
require_once __DIR__ . '/ComplaintHandlerPlugin.php';

return [
    'class'       => ComplaintHandlerPlugin::class,
    'name'        => 'Complaint Handler',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'Deteksi komplain pada flow order chat, routing AI vs human follow-up, dan ticketing komplain cabang.',
    'requires'    => '1.0.0',
];
