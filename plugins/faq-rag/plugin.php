<?php

declare(strict_types=1);

require_once __DIR__ . '/FaqVectorService.php';
require_once __DIR__ . '/FaqRepository.php';
require_once __DIR__ . '/FaqRagResponder.php';
require_once __DIR__ . '/FaqSkill.php';
require_once __DIR__ . '/FaqRagPlugin.php';

return [
    'class'       => FaqRagPlugin::class,
    'name'        => 'FAQ RAG',
    'version'     => '1.0.0',
    'author'      => 'KopiBot Team',
    'description' => 'FAQ global dan per cabang dengan vector database lokal, dashboard admin, dan skill FAQ untuk chat.',
    'requires'    => '1.0.0',
];
