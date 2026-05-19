<?php

declare(strict_types=1);

require_once __DIR__ . '/ExampleFaqSkill.php';
require_once __DIR__ . '/ExampleFaqSkillPlugin.php';

return [
    'class'       => ExampleFaqSkillPlugin::class,
    'name'        => 'Example Skill Plugin',
    'version'     => '1.0.0',
    'author'      => 'Toko Kopi',
    'description' => 'Contoh plugin skill chatbot yang bisa dijadikan template oleh developer lain.',
    'requires'    => '1.0.0',
];
