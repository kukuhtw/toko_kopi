<?php

declare(strict_types=1);

require_once __DIR__ . '/ExamplePlugin.php';

return [
    'class'       => ExamplePlugin::class,
    'name'        => 'Example Plugin',
    'version'     => '1.0.0',
    'author'      => 'Developer',
    'description' => 'Template plugin minimal. Salin folder ini untuk membuat plugin baru.',
    'requires'    => '1.0.0',
];
