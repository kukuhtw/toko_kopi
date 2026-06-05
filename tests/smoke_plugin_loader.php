<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/../app/Config/config.php';

\KopiBot\Core\PluginLoader::reset();

$pluginsDir = sys_get_temp_dir() . '/kopibot-plugin-smoke-' . uniqid('', true);
mkdir($pluginsDir . '/demo-plugin', 0777, true);

file_put_contents($pluginsDir . '/plugins.json', json_encode([
    'demo-plugin' => [
        'active' => true,
    ],
], JSON_PRETTY_PRINT));

file_put_contents($pluginsDir . '/demo-plugin/plugin.php', <<<'PHP'
<?php

declare(strict_types=1);

class DemoSmokePlugin implements \App\Plugin\PluginInterface
{
    public function getName(): string
    {
        return 'Demo Smoke Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'KopiBot';
    }

    public function register(): void
    {
        \KopiBot\Core\HookManager::addFilter('plugin.smoke', static fn (string $value): string => $value . '-registered');
    }
}

return [
    'class' => DemoSmokePlugin::class,
    'name' => 'Demo Smoke Plugin',
    'version' => '1.0.0',
];
PHP);

\App\Plugin\PluginLoader::init($pluginsDir);

$checks = [
    'composer_loader_class' => class_exists(\KopiBot\Core\PluginLoader::class),
    'composer_interface_class' => interface_exists(\KopiBot\Contracts\PluginInterface::class),
    'legacy_loader_adapter_class' => class_exists(\App\Plugin\PluginLoader::class),
    'legacy_interface_adapter_class' => interface_exists(\App\Plugin\PluginInterface::class),
    'demo_plugin_loaded' => \App\Plugin\PluginLoader::isLoaded('demo-plugin'),
    'demo_plugin_filter' => \KopiBot\Core\HookManager::applyFilters('plugin.smoke', 'demo') === 'demo-registered',
];

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
