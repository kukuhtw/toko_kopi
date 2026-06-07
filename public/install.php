<?php

declare(strict_types=1);

define('APP_NAME_DEFAULT', 'AI Agent Commerce');
define('APP_BRAND_DEFAULT', 'Toko Kopi');

define('ROOT', dirname(__DIR__));
define('LOCK_FILE', ROOT . '/storage/installed.lock');
define('DB_DIR', ROOT . '/database');

if (file_exists(LOCK_FILE) && !isset($_GET['force'])) {
    die(renderShell('Sudah Terinstal', '
        <div class="card">
            <h2 style="color:#6f4e37">Aplikasi Sudah Terinstal</h2>
            <p>File <code>storage/installed.lock</code> ditemukan. Instalasi sudah selesai sebelumnya.</p>
            <p>Untuk instal ulang, hapus file lock tersebut atau akses <code>install.php?force=1</code>.</p>
            <p><a class="btn" href="index.php">Buka Aplikasi</a>&nbsp;
               <a class="btn btn-secondary" href="login.php">Login</a></p>
        </div>'));
}

session_name('toko_kopi_installer');
session_start();

$step = max(1, min(6, (int) ($_GET['step'] ?? 1)));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1_next') {
        header('Location: install.php?step=2');
        exit;
    }

    if ($action === 'step2_save') {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = sanitizeDatabaseName(trim($_POST['db_name'] ?? 'toko_kopi'));
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            $errors[] = 'Host, nama database, dan user wajib diisi.';
        } elseif (!preg_match('/^\w+$/', $dbName)) {
            $errors[] = 'Nama database hanya boleh berisi huruf, angka, dan underscore.';
        } else {
            try {
                $testPdo = new PDO(
                    "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                $testPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); // NOSONAR - identifier safely escaped
                unset($testPdo);

                $_SESSION['db'] = compact('dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass');
                header('Location: install.php?step=3');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Koneksi atau pembuatan database gagal: ' . $e->getMessage();
            }
        }
        $step = 2;
    }

    if ($action === 'step3_save') {
        $appName = trim($_POST['app_name'] ?? APP_NAME_DEFAULT);
        $brandEmoji = mb_substr(strip_tags((string)($_POST['brand_emoji'] ?? '')), 0, 8);
        $tagline = mb_substr(strip_tags((string)($_POST['tagline'] ?? '')), 0, 120);
        $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
        $appEnv = in_array($_POST['app_env'] ?? 'production', ['development', 'production'], true) ? $_POST['app_env'] : 'production';

        if ($appName === '' || $baseUrl === '') {
            $errors[] = 'Nama aplikasi dan Base URL wajib diisi.';
            $step = 3;
        } else {
            $_SESSION['app'] = compact('appName', 'brandEmoji', 'tagline', 'baseUrl', 'appEnv');
            header('Location: install.php?step=4');
            exit;
        }
    }

    if ($action === 'step4_save') {
        $adminName = trim($_POST['admin_name'] ?? 'Super Admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email super admin tidak valid.';
        } elseif (strlen($adminPass) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        } elseif ($adminPass !== $adminPass2) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } else {
            $_SESSION['admin'] = compact('adminName', 'adminEmail', 'adminPass');
            header('Location: install.php?step=5');
            exit;
        }
        $step = 4;
    }

    if ($action === 'step5_save_plugins') {
        $available = array_column(discoverPlugins(), 'slug');
        $selected = array_values(array_intersect($available, (array)($_POST['plugins'] ?? [])));
        $catalogTemplate = $_POST['catalog_template'] ?? 'keep-seed';
        $templates = array_column(getCatalogTemplateOptions(), 'slug');
        if (!in_array($catalogTemplate, $templates, true)) {
            $catalogTemplate = 'keep-seed';
        }
        if ($catalogTemplate !== 'keep-seed' && in_array($catalogTemplate, $available, true) && !in_array($catalogTemplate, $selected, true)) {
            $selected[] = $catalogTemplate;
        }
        $_SESSION['plugins'] = $selected;
        $_SESSION['catalog_template'] = $catalogTemplate;
        if (isset($_SESSION['app']) && is_array($_SESSION['app'])) {
            $_SESSION['app'] = applyTemplateBrandingDefaults($_SESSION['app'], $catalogTemplate);
        }
        header('Location: install.php?step=6');
        exit;
    }

    if ($action === 'run_install') {
        $result = runInstallation();
        if ($result['success']) {
            header('Location: install.php?done=1');
            exit;
        }
        $errors = $result['errors'];
        $step = 6;
    }
}

if (isset($_GET['done'])) {
    $admin = $_SESSION['admin'] ?? [];
    $template = $_SESSION['catalog_template'] ?? 'keep-seed';
    session_destroy();
    echo renderShell('Instalasi Berhasil', '
        <div class="card" style="text-align:center">
            <div style="font-size:4rem">🛒</div>
            <h2 style="color:#6f4e37;margin:12px 0">Instalasi Berhasil!</h2>
            <p>Aplikasi AI Agent Commerce siap digunakan.</p>
            <div class="alert alert-success" style="text-align:left">
                <strong>Akun Super Admin:</strong><br>
                Email: <code>' . htmlspecialchars($admin['adminEmail'] ?? '-') . '</code><br>
                Password: <em>yang kamu masukkan tadi</em><br>
                Template katalog: <code>' . htmlspecialchars($template) . '</code>
            </div>
            <div class="alert alert-warning" style="text-align:left">
                ⚠️ <strong>Penting:</strong> Segera hapus atau rename file <code>public/install.php</code>
                dari server untuk keamanan.
            </div>
            <a class="btn" href="login.php">Login ke Dashboard</a>
        </div>');
    exit;
}

$body = match ($step) {
    1 => renderStep1(),
    2 => renderStep2($errors),
    3 => renderStep3($errors),
    4 => renderStep4($errors),
    5 => renderStep5($errors),
    6 => renderStep6($errors),
    default => renderStep1(),
};

echo renderShell("Instalasi — Langkah {$step} dari 6", $body);

function runInstallation(): array
{
    $errors = [];
    $db = $_SESSION['db'] ?? [];
    $app = $_SESSION['app'] ?? [];
    $admin = $_SESSION['admin'] ?? [];
    $plugins = $_SESSION['plugins'] ?? getDefaultPluginSelection();

    if (empty($db) || empty($app) || empty($admin)) {
        return ['success' => false, 'errors' => ['Data sesi tidak lengkap. Mulai dari langkah 1.']];
    }

    try {
        $pdo = new PDO(
            "mysql:host={$db['dbHost']};port={$db['dbPort']};charset=utf8mb4",
            $db['dbUser'],
            $db['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $safeDb = str_replace('`', '``', $db['dbName']);
        $pdo->exec('DROP DATABASE IF EXISTS `' . $safeDb . '`'); // NOSONAR - identifier safely escaped with backtick doubling
        $pdo->exec('CREATE DATABASE `' . $safeDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); // NOSONAR
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Koneksi DB atau create database gagal: ' . $e->getMessage()]];
    }

    try {
        $pdo = new PDO(
            "mysql:host={$db['dbHost']};port={$db['dbPort']};dbname={$db['dbName']};charset=utf8mb4",
            $db['dbUser'],
            $db['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Koneksi ke database gagal: ' . $e->getMessage()]];
    }

    $databaseBootstrapErrors = runInstallerDatabaseBootstrap($pdo);
    if ($databaseBootstrapErrors) {
        $errors = array_merge($errors, $databaseBootstrapErrors);
    }

    try {
        $hash = password_hash($admin['adminPass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE role = ? LIMIT 1')
            ->execute([$admin['adminName'], $admin['adminEmail'], $hash, 'super_admin']);
    } catch (PDOException $e) {
        $errors[] = 'Update admin gagal: ' . $e->getMessage();
    }

    // Tulis .env dan plugins.json lebih awal agar Database::getInstance() bisa dipakai oleh template
    if (file_put_contents(ROOT . '/.env', buildEnvContent($db, $app)) === false) {
        $errors[] = 'Gagal menulis file .env. Pastikan folder root dapat ditulis.';
    }

    $catalogTemplate = $_SESSION['catalog_template'] ?? 'keep-seed';

    $settingsError = persistInstalledAppSettings($pdo, $app, $catalogTemplate);
    if ($settingsError !== null) {
        $errors[] = $settingsError;
    }

    if (!writePluginsConfig($plugins)) {
        $errors[] = 'Gagal menulis file plugins/plugins.json.';
    }

    $errors = array_merge($errors, seedCatalogTemplateIfNeeded($catalogTemplate, $db));

    createStorageDirs();

    if (file_put_contents(LOCK_FILE, date('Y-m-d H:i:s')) === false) {
        $errors[] = 'Gagal membuat storage/installed.lock. Folder storage mungkin tidak writable.';
    }

    return empty($errors) ? ['success' => true, 'errors' => []] : ['success' => false, 'errors' => $errors];
}

function seedCatalogTemplateIfNeeded(string $catalogTemplate, array $db): array
{
    if ($catalogTemplate === 'keep-seed') {
        return [];
    }
    $bootstrapError = bootstrapInstallerTemplateRuntime($db);
    if ($bootstrapError !== null) {
        return ['[template] ' . $bootstrapError];
    }
    $templateErrors = applyCatalogTemplate($catalogTemplate);
    return array_map(fn(string $e): string => "[template] $e", $templateErrors);
}

function createStorageDirs(): void
{
    @mkdir(ROOT . '/storage', 0755, true);
    @mkdir(ROOT . '/storage/logs', 0755, true);
    @mkdir(ROOT . '/uploads', 0755, true);
}

function applyCatalogTemplate(string $slug): array
{
    $classMap = [
        'coffee-template'           => 'CoffeeTemplatePlugin',
        'bakery-template'           => 'BakeryTemplatePlugin',
        'fruit-template'            => 'FruitTemplatePlugin',
        'meat-veggie-template'      => 'MeatVeggieTemplatePlugin',
        'pharmacy-template'         => 'PharmacyTemplatePlugin',
        'indonesian-resto-template' => 'RestoIndonesiaTemplatePlugin',
        'minimarket-template'       => 'MinimarketTemplatePlugin',
        'warung-template'           => 'WarungTemplatePlugin',
        'resto-baso-template'       => 'RestoBasoTemplatePlugin',
        'kebab-template'            => 'KebabTemplatePlugin',
        'burger-template'           => 'BurgerTemplatePlugin',
        'hp-accessories-template'   => 'HpAccessoriesTemplatePlugin',
        'fashion-wanita-template'   => 'FashionWanitaTemplatePlugin',
        'tours-travel-template'     => 'ToursTravelTemplatePlugin',
        'umrah-template'            => 'UmrahTemplatePlugin',
    ];

    $className = $classMap[$slug] ?? null;
    if ($className === null) {
        return ["Template '{$slug}' tidak dikenal atau belum mendukung seeding otomatis."];
    }

    $pluginDir = ROOT . '/plugins/' . $slug;
    foreach (glob($pluginDir . '/*.php') ?: [] as $file) {
        if (basename($file) !== 'plugin.php') {
            require_once $file;
        }
    }

    if (!class_exists($className)) {
        return ["Kelas '{$className}' tidak ditemukan di plugin '{$slug}'."];
    }

    if (!method_exists($className, 'resetAndSeed')) {
        return ["Template '{$slug}' belum memiliki method resetAndSeed()."];
    }

    $result = (new $className())->resetAndSeed();

    return ($result['success'] ?? false) ? [] : [$result['message'] ?? 'Template seeding gagal.'];
}

function bootstrapInstallerTemplateRuntime(array $db): ?string
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return null;
    }

    $requiredKeys = ['dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $db)) {
            return "Konfigurasi database untuk template tidak lengkap: '{$key}' belum tersedia.";
        }
    }

    $dbConstants = [
        'DB_HOST' => (string) $db['dbHost'],
        'DB_PORT' => (string) $db['dbPort'],
        'DB_NAME' => (string) $db['dbName'],
        'DB_USER' => (string) $db['dbUser'],
        'DB_PASS' => (string) $db['dbPass'],
    ];

    foreach ($dbConstants as $constant => $value) {
        if (!defined($constant)) {
            define($constant, $value);
        }
    }

    $bootstrapFile = ROOT . '/bootstrap.php';
    if (file_exists($bootstrapFile)) {
        require_once $bootstrapFile;
    } else {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';

            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = ROOT . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    $bootstrapped = true;

    return null;
}

function bootstrapInstallerComposerAutoload(): bool
{
    static $autoloaded = false;

    if ($autoloaded) {
        return true;
    }

    $autoloadFile = ROOT . '/vendor/autoload.php';
    if (!file_exists($autoloadFile)) {
        return false;
    }

    require_once $autoloadFile;
    $autoloaded = true;

    return true;
}

function runInstallerDatabaseBootstrap(PDO $pdo): array
{
    $migrationDir = DB_DIR . '/migrations';
    $seederDir = DB_DIR . '/seeders';

    if (is_dir($migrationDir) && bootstrapInstallerComposerAutoload() && class_exists(\KopiBot\Core\MigrationRunner::class)) {
        $runner = new \KopiBot\Core\MigrationRunner($pdo);
        $results = $runner->run($migrationDir);
        $errors = collectMigrationErrors($results, 'migration');

        if ($errors !== []) {
            return $errors;
        }

        if (is_dir($seederDir)) {
            $seedErrors = executeSqlFilesInDirectory($pdo, $seederDir, 'seeder');
            if ($seedErrors !== []) {
                return $seedErrors;
            }
        } elseif (file_exists(DB_DIR . '/seed.sql')) {
            $seedErrors = executeSqlFile($pdo, DB_DIR . '/seed.sql');
            if ($seedErrors !== []) {
                return array_map(fn($e) => "[seed] $e", $seedErrors);
            }
        }

        return [];
    }

    $schemaFile = DB_DIR . '/schema.sql';
    if (!file_exists($schemaFile)) {
        return ['File database/schema.sql tidak ditemukan dan migration runner Composer tidak tersedia.'];
    }

    $errors = [];
    $schemaErrors = executeSqlFile($pdo, $schemaFile);
    if ($schemaErrors) {
        $errors = array_merge($errors, array_map(fn($e) => "[schema] $e", $schemaErrors));
    }

    $seedFile = DB_DIR . '/seed.sql';
    if (file_exists($seedFile)) {
        $seedErrors = executeSqlFile($pdo, $seedFile);
        if ($seedErrors) {
            $errors = array_merge($errors, array_map(fn($e) => "[seed] $e", $seedErrors));
        }
    }

    return $errors;
}

function collectMigrationErrors(array $results, string $label): array
{
    $errors = [];

    foreach ($results as $result) {
        if (($result['status'] ?? '') === 'failed') {
            $errors[] = sprintf(
                '[%s:%s] %s',
                $label,
                (string) ($result['migration'] ?? 'unknown'),
                (string) ($result['error'] ?? 'unknown error')
            );
        }
    }

    return $errors;
}

function executeSqlFilesInDirectory(PDO $pdo, string $directory, string $label): array
{
    $files = glob(rtrim($directory, '/\\') . '/*.sql') ?: [];
    sort($files);

    $errors = [];
    foreach ($files as $file) {
        $fileErrors = executeSqlFile($pdo, $file);
        if ($fileErrors !== []) {
            $errors = array_merge(
                $errors,
                array_map(
                    fn($e) => sprintf('[%s:%s] %s', $label, basename($file), $e),
                    $fileErrors
                )
            );
            break;
        }
    }

    return $errors;
}

function persistInstalledAppSettings(PDO $pdo, array $app, string $catalogTemplate): ?string
{
    try {
        $settings = [
            'app_name'         => (string)($app['appName'] ?? APP_NAME_DEFAULT),
            'theme_app_name'   => (string)($app['appName'] ?? APP_NAME_DEFAULT),
            'theme_brand_emoji'=> (string)($app['brandEmoji'] ?? ''),
            'theme_tagline'    => (string)($app['tagline'] ?? ''),
            'business_type'    => inferBusinessTypeFromTemplate($catalogTemplate),
            'catalog_template' => $catalogTemplate,
            'base_url'         => (string)($app['baseUrl'] ?? ''),
            'installed_at'     => date('c'),
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_val, description, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), description = VALUES(description), updated_at = NOW()'
        );

        $descriptions = [
            'app_name'         => 'Nama brand hasil instalasi',
            'theme_app_name'   => 'Nama brand untuk plugin theme hasil instalasi',
            'theme_brand_emoji'=> 'Emoji/icon brand untuk plugin theme hasil instalasi',
            'theme_tagline'    => 'Tagline bisnis untuk plugin theme hasil instalasi',
            'business_type'    => 'Tipe bisnis utama hasil instalasi',
            'catalog_template' => 'Template katalog yang dipilih saat instalasi',
            'base_url'         => 'Base URL aplikasi hasil instalasi',
            'installed_at'     => 'Waktu instalasi terakhir',
        ];

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $descriptions[$key] ?? '']);
        }
    } catch (PDOException $e) {
        return 'Gagal menyimpan profil aplikasi hasil instalasi: ' . $e->getMessage();
    }

    return null;
}

function inferBusinessTypeFromTemplate(string $catalogTemplate): string
{
    return match ($catalogTemplate) {
        'bakery-template'           => 'bakery',
        'fruit-template'            => 'toko buah',
        'meat-veggie-template'      => 'fresh market',
        'pharmacy-template'         => 'apotek',
        'indonesian-resto-template' => 'restaurant',
        'minimarket-template'       => 'mart',
        'tours-travel-template'     => 'travel',
        'umrah-template'            => 'umrah',
        default                     => 'coffee shop',
    };
}

function buildEnvContent(array $db, array $app): string
{
    return <<<ENV
# ============================================================
# AI Agent Commerce — Generated by Web Installer {$app['appName']}
# ============================================================

DB_HOST={$db['dbHost']}
DB_PORT={$db['dbPort']}
DB_NAME={$db['dbName']}
DB_USER={$db['dbUser']}
DB_PASS={$db['dbPass']}

APP_ENV={$app['appEnv']}
BASE_URL={$app['baseUrl']}

ANTHROPIC_API_KEY=
OPENROUTER_API_KEY=
ENV;
}

function executeSqlFile(PDO $pdo, string $filePath): array
{
    $sql = (string) file_get_contents($filePath);

    // utf8mb4_0900_ai_ci hanya tersedia di MySQL 8.0+.
    // Ganti ke utf8mb4_unicode_ci agar kompatibel dengan MySQL 5.7 dan MariaDB.
    $sql = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $sql);

    $stmts = splitSql($sql);
    $errors = [];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // errorInfo[1] = MySQL-specific error code; getCode() returns SQLSTATE string
            $code = (int)($e->errorInfo[1] ?? $e->getCode());
            if (!in_array($code, [1050, 1060, 1061, 1062, 1068, 1071, 1091, 1170], true)) {
                $errors[] = substr($stmt, 0, 80) . '… → ' . $e->getMessage();
            }
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    return $errors;
}

function splitSqlProcessChar(string $c, int $i, string $sql, bool &$inStr, string &$strChar, string &$current, array &$statements): void
{
    if ($inStr) {
        $current .= $c;
        if ($c === $strChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inStr = false;
        }
        return;
    }
    if ($c === '"' || $c === "'" || $c === '`') {
        $inStr = true;
        $strChar = $c;
        $current .= $c;
        return;
    }
    if ($c === ';') {
        $s = trim($current);
        if ($s !== '') {
            $statements[] = $s;
        }
        $current = '';
        return;
    }
    $current .= $c;
}

function splitSql(string $sql): array
{
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', (string)$sql);
    $statements = [];
    $current = '';
    $inStr = false;
    $strChar = '';

    for ($i = 0, $len = strlen((string)$sql); $i < $len; $i++) {
        splitSqlProcessChar($sql[$i], $i, $sql, $inStr, $strChar, $current, $statements);
    }

    $s = trim($current);
    if ($s !== '') {
        $statements[] = $s;
    }
    return $statements;
}

function renderStep1(): string
{
    $root = ROOT;
    $checks = [
        ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
        ['PDO', extension_loaded('pdo'), ''],
        ['PDO MySQL', extension_loaded('pdo_mysql'), ''],
        ['mbstring', extension_loaded('mbstring'), ''],
        ['json', extension_loaded('json'), ''],
        ['curl', extension_loaded('curl'), ''],
        ['openssl', extension_loaded('openssl'), ''],
        ['storage/ writable', is_writable("{$root}/storage") || @mkdir("{$root}/storage", 0755, true), ''],
        ['storage/logs/ writable', is_writable("{$root}/storage/logs") || @mkdir("{$root}/storage/logs", 0755, true), ''],
        ['uploads/ writable', is_writable("{$root}/uploads") || @mkdir("{$root}/uploads", 0755, true), ''],
        ['.env writable', is_writable($root) || is_writable("{$root}/.env") || !file_exists("{$root}/.env"), ''],
    ];
    $allPass = array_reduce($checks, fn($carry, $c) => $carry && $c[1], true);
    $rows = '';
    foreach ($checks as [$label, $pass, $detail]) {
        $icon = $pass ? '✅' : '❌';
        $class = $pass ? 'pass' : 'fail';
        $rows .= "<tr class=\"{$class}\"><td>{$icon}</td><td>{$label}</td><td>" . htmlspecialchars($detail) . '</td></tr>';
    }
    $btn = $allPass ? '<form method="POST"><input type="hidden" name="action" value="step1_next"><button type="submit" class="btn">Lanjut ke Langkah 2 &rarr;</button></form>' : '<p class="text-danger">Perbaiki masalah di atas sebelum melanjutkan.</p>';
    return "<div class=\"card\"><h2>Langkah 1 — Persyaratan Sistem</h2><p>Installer ini akan membantu Anda menyiapkan <strong>AI Agent Commerce</strong> — platform order berbasis AI chatbot untuk berbagai jenis bisnis — tanpa perlu edit file konfigurasi secara manual.</p><div class=\"alert alert-success\" style=\"margin:12px 0\"><strong>Apa yang akan disetup:</strong><ul style=\"margin:8px 0 0 16px;line-height:1.8\"><li>Database &amp; tabel (schema + data awal)</li><li>Konfigurasi aplikasi, brand, dan Base URL</li><li>Akun super admin</li><li>Template produk/menu sesuai jenis bisnis (coffee, pharmacy, bakery, mart, dll)</li><li>Plugin aktif: payment gateway, delivery, CRM, loyalti, dan lainnya</li></ul></div><p style=\"margin-bottom:12px\">Pastikan semua persyaratan di bawah sudah terpenuhi sebelum melanjutkan.</p><table class=\"req-table\"><tbody>{$rows}</tbody></table><div style=\"margin-top:20px\">{$btn}</div></div>";
}

function renderStep2(array $errors): string
{
    $saved = $_SESSION['db'] ?? [];
    $err = renderErrors($errors);
    $v = fn(string $k, string $d) => htmlspecialchars((string)($saved[$k] ?? $d));
    return "<div class=\"card\"><h2>Langkah 2 — Konfigurasi Database</h2>{$err}<div class=\"alert alert-success\">Installer akan melakukan <strong>test koneksi</strong> lalu menjalankan <code>CREATE DATABASE IF NOT EXISTS</code> — database dibuat otomatis selama user MySQL punya hak <em>create database</em>. Gunakan nama database yang mudah diingat dan sesuai jenis bisnis, contoh: <code>apotek_ai</code>, <code>toko_kopi</code>, <code>mart_commerce</code>.</div><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step2_save\"><div class=\"form-row\"><div class=\"form-group\"><label>Host Database</label><input type=\"text\" name=\"db_host\" class=\"form-control\" value=\"{$v('dbHost', 'localhost')}\" required></div><div class=\"form-group\" style=\"max-width:120px\"><label>Port</label><input type=\"number\" name=\"db_port\" class=\"form-control\" value=\"{$v('dbPort', '3306')}\" required></div></div><div class=\"form-group\"><label>Nama Database</label><input type=\"text\" name=\"db_name\" class=\"form-control\" value=\"{$v('dbName', 'toko_kopi')}\" required><small>Contoh: <code>toko_kopi</code>, <code>ai_commerce_pharmacy</code>, <code>ai_commerce_mart</code>. Hanya huruf, angka, underscore.</small></div><div class=\"form-row\"><div class=\"form-group\"><label>User Database</label><input type=\"text\" name=\"db_user\" class=\"form-control\" value=\"{$v('dbUser', 'root')}\" required></div><div class=\"form-group\"><label>Password Database</label><input type=\"password\" name=\"db_pass\" class=\"form-control\" value=\"\" placeholder=\"Kosongkan jika tidak ada\"></div></div><div class=\"form-nav\"><a href=\"install.php?step=1\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Test, Create DB &amp; Lanjut &rarr;</button></div></form></div>";
}

function renderStep3(array $errors): string
{
    $saved = $_SESSION['app'] ?? [];
    $catalogTemplate = (string)($_SESSION['catalog_template'] ?? 'keep-seed');
    if (is_array($saved)) {
        $saved = applyTemplateBrandingDefaults($saved, $catalogTemplate, false);
    }
    $err = renderErrors($errors);
    $autoUrl = detectBaseUrl();
    $brandEmoji = htmlspecialchars((string)($saved['brandEmoji'] ?? '☕'));
    $appName = htmlspecialchars((string)($saved['appName'] ?? APP_NAME_DEFAULT));
    $tagline = htmlspecialchars((string)($saved['tagline'] ?? ''));
    $baseUrl = htmlspecialchars((string)($saved['baseUrl'] ?? $autoUrl));
    $devSel = ($saved['appEnv'] ?? 'production') === 'development' ? 'selected' : '';
    $prodSel = ($saved['appEnv'] ?? 'production') === 'production' ? 'selected' : '';
    $templateLabel = htmlspecialchars(getCatalogTemplateInfo($catalogTemplate)['name']);
    return "<div class=\"card\"><h2>Langkah 3 — Pengaturan Aplikasi</h2>{$err}<form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step3_save\"><div class=\"form-row\"><div class=\"form-group\" style=\"max-width:120px\"><label>Icon Toko</label><input type=\"text\" id=\"brand_emoji\" name=\"brand_emoji\" class=\"form-control\" value=\"{$brandEmoji}\" placeholder=\"☕\" maxlength=\"8\" style=\"font-size:1.2rem;text-align:center\"><small>Satu emoji/icon brand.</small></div><div class=\"form-group\" style=\"flex:1\"><label>Nama Brand / Toko</label><input type=\"text\" id=\"app_name\" name=\"app_name\" class=\"form-control\" value=\"{$appName}\" required placeholder=\"AI Commerce Mart\"><small>Contoh: KopiBot Cafe, Fresh Mart AI, Pharmacy Agent, Bakery Commerce.</small></div></div><div class=\"form-group\"><label>Tagline Bisnis</label><input type=\"text\" id=\"tagline\" name=\"tagline\" class=\"form-control\" value=\"{$tagline}\" placeholder=\"Premium Coffee Experience\" maxlength=\"120\"><small>Akan dipakai juga di halaman Tema & Branding setelah instalasi.</small></div><div class=\"alert alert-success\" style=\"font-size:.82rem\">Default branding akan menyesuaikan template bisnis yang dipilih. Template saat ini: <strong>{$templateLabel}</strong>.</div><div style=\"margin:-2px 0 18px\"><div style=\"font-size:.75rem;font-weight:700;color:#8b6f47;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px\">Preview Sidebar Logo</div><div id=\"brand-preview\" style=\"background:#2c1a0e;color:#fff;display:inline-block;padding:18px 22px;border-radius:12px;min-width:290px;box-shadow:0 8px 24px rgba(44,26,14,.16)\"><div style=\"display:flex;align-items:flex-start;gap:8px\"><span id=\"preview-emoji\" style=\"font-size:1.15rem;line-height:1.2\">{$brandEmoji}</span><div><div id=\"preview-name\" style=\"font-size:1.08rem;font-weight:700;line-height:1.2\">{$appName}</div><div id=\"preview-tagline\" style=\"font-size:.68rem;color:rgba(255,255,255,.45);margin-top:4px;font-weight:400;letter-spacing:.3px;display:" . (($saved['tagline'] ?? '') !== '' ? 'block' : 'none') . "\">{$tagline}</div></div></div></div></div><div class=\"form-group\"><label>Base URL</label><input type=\"url\" name=\"base_url\" class=\"form-control\" value=\"{$baseUrl}\" required><small>URL lengkap ke folder <code>public/</code>. Contoh: <code>http://localhost/toko_kopi/public</code></small></div><div class=\"form-group\"><label>Lingkungan</label><select name=\"app_env\" class=\"form-control\"><option value=\"production\" {$prodSel}>Production</option><option value=\"development\" {$devSel}>Development (tampilkan error)</option></select></div><div class=\"form-nav\"><a href=\"install.php?step=2\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Lanjut &rarr;</button></div></form><script>(function(){function escH(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}function buildNameHtml(name){var words=name.split(/\\s+/).filter(Boolean);if(!words.length){return 'Toko <span style=\"color:#d4a574\">Kopi</span>';}var last=words.pop();return (words.length?escH(words.join(' '))+' ':'')+'<span style=\"color:#d4a574\">'+escH(last)+'</span>';}function updateBrandPreview(){var emoji=document.getElementById('brand_emoji').value.trim()||'☕';var name=document.getElementById('app_name').value.trim()||'Toko Kopi';var tagline=document.getElementById('tagline').value.trim();document.getElementById('preview-emoji').textContent=emoji;document.getElementById('preview-name').innerHTML=buildNameHtml(name);var taglineEl=document.getElementById('preview-tagline');taglineEl.textContent=tagline;taglineEl.style.display=tagline!==''?'block':'none';}['brand_emoji','app_name','tagline'].forEach(function(id){var el=document.getElementById(id);if(el){el.addEventListener('input',updateBrandPreview);}});updateBrandPreview();})();</script></div>";
}

function applyTemplateBrandingDefaults(array $app, string $catalogTemplate, bool $replaceGeneric = true): array
{
    $defaults = getBrandingDefaultsForTemplate($catalogTemplate);
    $currentName = trim((string)($app['appName'] ?? ''));
    $currentEmoji = trim((string)($app['brandEmoji'] ?? ''));
    $currentTagline = trim((string)($app['tagline'] ?? ''));

    $genericNames = [APP_NAME_DEFAULT, 'AI Commerce Mart', 'Toko Kopi'];
    $genericEmojis = ['', '☕'];
    $genericTaglines = ['', 'Premium Coffee Experience'];

    if ($currentName === '' || ($replaceGeneric && in_array($currentName, $genericNames, true))) {
        $app['appName'] = $defaults['appName'];
    }
    if ($currentEmoji === '' || ($replaceGeneric && in_array($currentEmoji, $genericEmojis, true))) {
        $app['brandEmoji'] = $defaults['brandEmoji'];
    }
    if ($currentTagline === '' || ($replaceGeneric && in_array($currentTagline, $genericTaglines, true))) {
        $app['tagline'] = $defaults['tagline'];
    }

    return $app;
}

function getBrandingDefaultsForTemplate(string $catalogTemplate): array
{
    return match ($catalogTemplate) {
        'bakery-template' => [
            'appName' => 'Bakery Commerce',
            'brandEmoji' => '🥐',
            'tagline' => 'Freshly Baked Every Day',
        ],
        'fruit-template' => [
            'appName' => 'Fresh Fruit Market',
            'brandEmoji' => '🍎',
            'tagline' => 'Buah Segar, Cepat Sampai',
        ],
        'meat-veggie-template' => [
            'appName' => 'Fresh Market',
            'brandEmoji' => '🥬',
            'tagline' => 'Belanja Bahan Segar Jadi Mudah',
        ],
        'pharmacy-template' => [
            'appName' => 'Apotek Digital',
            'brandEmoji' => '💊',
            'tagline' => 'Obat, Vitamin, dan Alat Kesehatan Siap Order',
        ],
        'minimarket-template' => [
            'appName' => 'Smart Mart',
            'brandEmoji' => '🛒',
            'tagline' => 'Belanja Harian Lebih Praktis',
        ],
        'indonesian-resto-template' => [
            'appName' => 'Resto Nusantara',
            'brandEmoji' => '🍽️',
            'tagline' => 'Menu Favorit Nusantara, Siap Dipesan',
        ],
        'warung-template' => [
            'appName' => 'Warung Makan',
            'brandEmoji' => '🍽️',
            'tagline' => 'Makan Enak, Harga Warung',
        ],
        'resto-baso-template' => [
            'appName' => 'Warung Baso',
            'brandEmoji' => '🍜',
            'tagline' => 'Baso Segar, Kuah Gurih, Pesan Sekarang',
        ],
        'kebab-template' => [
            'appName' => 'Kebab House',
            'brandEmoji' => '🌯',
            'tagline' => 'Kebab Segar, Renyah, dan Kaya Rempah',
        ],
        'burger-template' => [
            'appName' => 'Burger Joint',
            'brandEmoji' => '🍔',
            'tagline' => 'Burger Juicy, Sides Crispy, Pesan Sekarang',
        ],
        'hp-accessories-template' => [
            'appName' => 'Aksesori HP Store',
            'brandEmoji' => '📱',
            'tagline' => 'Lengkapi HP-mu, Pesan Langsung via Chat',
        ],
        'fashion-wanita-template' => [
            'appName' => 'Butik Fashion',
            'brandEmoji' => '👗',
            'tagline' => 'Tampil Cantik, Belanja Mudah',
        ],
        'tours-travel-template' => [
            'appName' => 'Nusantara Travel',
            'brandEmoji' => '✈️',
            'tagline' => 'Paket Wisata Siap Booking, Konsultasi Cepat',
        ],
        'umrah-template' => [
            'appName' => 'Umrah Amanah',
            'brandEmoji' => '🕋',
            'tagline' => 'Paket Umrah Nyaman, Ibadah Lebih Tenang',
        ],
        default => [
            'appName' => 'Toko Kopi',
            'brandEmoji' => '☕',
            'tagline' => 'Premium Coffee Experience',
        ],
    };
}

function renderStep4(array $errors): string
{
    $saved = $_SESSION['admin'] ?? [];
    $err = renderErrors($errors);
    $name = htmlspecialchars((string)($saved['adminName'] ?? 'Super Admin'));
    $email = htmlspecialchars((string)($saved['adminEmail'] ?? ''));
    return "<div class=\"card\"><h2>Langkah 4 — Akun Super Admin</h2>{$err}<p>Akun ini akan menjadi administrator utama dengan akses penuh.</p><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step4_save\"><div class=\"form-group\"><label>Nama</label><input type=\"text\" name=\"admin_name\" class=\"form-control\" value=\"{$name}\" required></div><div class=\"form-group\"><label>Email</label><input type=\"email\" name=\"admin_email\" class=\"form-control\" value=\"{$email}\" required placeholder=\"admin@toko-kamu.com\"></div><div class=\"form-row\"><div class=\"form-group\"><label>Password <small>(min 8 karakter)</small></label><input type=\"password\" name=\"admin_pass\" class=\"form-control\" minlength=\"8\" required autocomplete=\"new-password\"></div><div class=\"form-group\"><label>Konfirmasi Password</label><input type=\"password\" name=\"admin_pass2\" class=\"form-control\" minlength=\"8\" required autocomplete=\"new-password\"></div></div><div class=\"form-nav\"><a href=\"install.php?step=3\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Lanjut &rarr;</button></div></form></div>";
}

function renderStep5(array $errors): string
{
    $err = renderErrors($errors);
    $plugins = discoverPlugins();
    $selected = $_SESSION['plugins'] ?? getDefaultPluginSelection();
    $catalogTemplate = $_SESSION['catalog_template'] ?? 'keep-seed';
    $templateCards = '';
    foreach (getCatalogTemplateOptions() as $option) {
        $checked = $catalogTemplate === $option['slug'] ? 'checked' : '';
        $brand = getBrandingDefaultsForTemplate((string)$option['slug']);
        $previewEmoji = htmlspecialchars((string)($brand['brandEmoji'] ?? '☕'));
        $previewName = htmlspecialchars((string)($brand['appName'] ?? 'Toko Kopi'));
        $previewTagline = htmlspecialchars((string)($brand['tagline'] ?? ''));
        $templateCards .= '<label style="display:block;border:1px solid ' . ($checked ? '#a0522d' : '#e0d4c8') . ';border-radius:12px;padding:14px 16px;margin-bottom:10px;cursor:pointer;background:' . ($checked ? '#fff8f3' : '#fff') . ';box-shadow:' . ($checked ? '0 0 0 3px rgba(160,82,45,.08)' : 'none') . '"><div style="display:flex;gap:12px"><input type="radio" name="catalog_template" value="' . htmlspecialchars($option['slug']) . '" ' . $checked . ' style="margin-top:3px"><div style="flex:1"><div style="font-weight:700;color:#6f4e37">' . htmlspecialchars($option['name']) . '</div><div style="font-size:.82rem;color:#8b6f47;margin-top:4px">' . htmlspecialchars($option['description']) . '</div><div style="font-size:.78rem;color:#a08a72;margin-top:6px">Contoh produk: ' . htmlspecialchars($option['examples']) . '</div><div style="margin-top:10px"><div style="font-size:.68rem;font-weight:700;color:#8b6f47;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Preview Branding</div><div style="background:#2c1a0e;color:#fff;display:inline-block;min-width:230px;padding:12px 14px;border-radius:10px;box-shadow:0 6px 18px rgba(44,26,14,.12)"><div style="display:flex;align-items:flex-start;gap:8px"><span style="font-size:1rem;line-height:1.2">' . $previewEmoji . '</span><div><div style="font-size:.94rem;font-weight:700;line-height:1.2">' . $previewName . '</div><div style="font-size:.66rem;color:rgba(255,255,255,.48);margin-top:4px;letter-spacing:.2px">' . $previewTagline . '</div></div></div></div></div></div></div></label>';
    }
    $cards = '';
    foreach ($plugins as $plugin) {
        $checked = in_array($plugin['slug'], $selected, true) ? 'checked' : '';
        $cards .= '<label style="display:block;border:1px solid #e0d4c8;border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;background:#fffaf6"><div style="display:flex;align-items:flex-start;gap:12px"><input type="checkbox" name="plugins[]" value="' . htmlspecialchars($plugin['slug']) . '" ' . $checked . ' style="margin-top:3px"><div><div style="font-weight:700;color:#6f4e37">' . htmlspecialchars($plugin['name']) . '</div><div style="font-size:.82rem;color:#8b6f47;margin-top:4px">' . htmlspecialchars($plugin['description']) . '</div><div style="font-size:.75rem;color:#a08a72;margin-top:6px"><code>' . htmlspecialchars($plugin['slug']) . '</code></div></div></div></label>';
    }
    if ($cards === '') {
        $cards = '<div class="alert alert-warning">Belum ada plugin yang ditemukan di folder <code>plugins/</code>.</div>';
    }
    return "<div class=\"card\"><h2>Langkah 5 — Pilih Contoh Data Produk & Plugin</h2>{$err}<p>Pilih <strong>template data produk</strong> yang paling sesuai dengan jenis bisnis Anda. Template ini mengisi database dengan contoh produk, kategori, dan harga — sehingga aplikasi langsung bisa dicoba setelah instalasi selesai. Anda dapat mengubah, menambah, atau menghapus produk kapan saja melalui dashboard admin.</p><div class=\"alert alert-success\" style=\"margin:12px 0;font-size:.85rem\"><strong>Tips memilih template:</strong> Pilih yang paling mendekati jenis bisnis Anda. Branding default (nama toko, icon, tagline) akan menyesuaikan template jika Anda belum mengisi di langkah sebelumnya.</div><h3 style=\"color:#6f4e37;margin:18px 0 10px\">Template Data Produk / Menu</h3><p style=\"font-size:.85rem;color:#8b6f47;margin-bottom:12px\">Setiap kartu menampilkan preview branding default yang akan dipakai installer.</p><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step5_save_plugins\">{$templateCards}<h3 style=\"color:#6f4e37;margin:22px 0 10px\">Plugin Aktif</h3><p style=\"font-size:.88rem;color:#8b6f47\">Centang plugin yang ingin diaktifkan. Plugin dapat diaktifkan atau dinonaktifkan kapan saja dari dashboard setelah instalasi.<br>Kategori plugin: <strong>Payment Gateway</strong> (Midtrans, Xendit, iPaymu, Nicepay) · <strong>Channel Chat</strong> (WhatsApp, Telegram, Discord) · <strong>Delivery</strong> (GoSend, RajaOngkir, KiriminAja) · <strong>CRM & Loyalty</strong> · <strong>FAQ &amp; Complaint</strong> · <strong>POS Connector</strong> · <strong>Upselling &amp; Promo</strong></p><div style=\"margin-top:18px\">{$cards}</div><div class=\"form-nav\"><a href=\"install.php?step=4\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Lanjut &rarr;</button></div></form></div>";
}

function renderStep6(array $errors): string
{
    $db = $_SESSION['db'] ?? [];
    $app = $_SESSION['app'] ?? [];
    $admin = $_SESSION['admin'] ?? [];
    $plugins = $_SESSION['plugins'] ?? getDefaultPluginSelection();
    $catalogTemplate = $_SESSION['catalog_template'] ?? 'keep-seed';
    $templateInfo = getCatalogTemplateInfo($catalogTemplate);
    $err = renderErrors($errors);
    $dbSummary = sprintf('%s:%s / %s (user: %s)', htmlspecialchars($db['dbHost'] ?? ''), htmlspecialchars($db['dbPort'] ?? ''), htmlspecialchars($db['dbName'] ?? ''), htmlspecialchars($db['dbUser'] ?? ''));
    return "<div class=\"card\"><h2>Langkah 6 — Ringkasan &amp; Jalankan Instalasi</h2>{$err}<p style=\"margin-bottom:14px\">Periksa kembali konfigurasi di bawah sebelum menjalankan instalasi.</p><table class=\"summary-table\"><tr><th>Database</th><td>{$dbSummary}</td></tr><tr><th>Base URL</th><td>" . htmlspecialchars($app['baseUrl'] ?? '') . "</td></tr><tr><th>Lingkungan</th><td>" . htmlspecialchars($app['appEnv'] ?? '') . "</td></tr><tr><th>Icon Brand</th><td>" . htmlspecialchars($app['brandEmoji'] ?? '☕') . "</td></tr><tr><th>Nama Brand</th><td>" . htmlspecialchars($app['appName'] ?? '') . "</td></tr><tr><th>Tagline</th><td>" . htmlspecialchars($app['tagline'] ?? '-') . "</td></tr><tr><th>Admin Email</th><td>" . htmlspecialchars($admin['adminEmail'] ?? '') . "</td></tr><tr><th>Template Produk</th><td>" . htmlspecialchars($templateInfo['name']) . "<br><small style=\"color:#8b6f47\">" . htmlspecialchars($templateInfo['examples']) . "</small></td></tr><tr><th>Plugin Aktif</th><td>" . htmlspecialchars(implode(', ', $plugins ?: ['Tidak ada'])) . "</td></tr></table><p style=\"margin:16px 0 8px\"><strong>Proses instalasi akan melakukan:</strong></p><ul style=\"padding-left:18px;line-height:2\"><li>Membuat database dan semua tabel dari <code>database/schema.sql</code></li><li>Mengisi data awal dari <code>database/seed.sql</code></li><li>Mengisi contoh produk/menu sesuai template yang dipilih</li><li>Menyimpan branding toko (icon, nama, tagline) ke pengaturan Tema</li><li>Membuat akun super admin dengan email dan password yang diisi</li><li>Mengaktifkan plugin yang dipilih di <code>plugins/plugins.json</code></li><li>Menulis konfigurasi aplikasi ke file <code>.env</code></li><li>Membuat <code>storage/installed.lock</code> sebagai tanda instalasi selesai</li></ul><div class=\"alert alert-warning\" style=\"margin-top:16px\"><strong>Perhatian:</strong><ul style=\"margin:6px 0 0 16px;line-height:1.8\"><li>Proses ini <strong>tidak dapat dibatalkan</strong>. Pastikan semua data sudah benar.</li><li>Jika ingin instal ulang, hapus file <code>storage/installed.lock</code> atau akses <code>install.php?force=1</code>.</li><li>Plugin template (coffee, bakery, pharmacy, dll) berfungsi sebagai seed data yang dapat di-reset dari dashboard admin setelah instalasi.</li></ul></div><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"run_install\"><div class=\"form-nav\"><a href=\"install.php?step=5\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn btn-success\">&#9889; Jalankan Instalasi</button></div></form></div>";
}

function detectBaseUrl(): string
{
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/install.php';
    $dir = rtrim(str_replace('/install.php', '', strtok($uri, '?')), '/');
    return $proto . '://' . $host . $dir;
}

function renderErrors(array $errors): string
{
    if (empty($errors)) {
        return '';
    }
    $items = implode('', array_map(fn($e) => '<li>' . htmlspecialchars($e) . '</li>', $errors));
    return "<div class=\"alert alert-error\"><ul style=\"margin:0;padding-left:18px\">{$items}</ul></div>";
}

function sanitizeDatabaseName(string $name): string
{
    return preg_replace('/\W/', '_', $name) ?: 'toko_kopi';
}

function getCatalogTemplateOptions(): array
{
    return [
        ['slug' => 'keep-seed', 'name' => 'Default Seed Coffee Menu', 'description' => 'Gunakan data awal dari database/seed.sql. Cocok untuk demo cepat karena seed saat ini berisi contoh menu kopi lengkap.', 'examples' => 'Espresso, Americano, Cappuccino, Latte, Flat White, Kopi Tubruk'],
        ['slug' => 'coffee-template', 'name' => 'Coffee Shop Template', 'description' => 'Aktifkan plugin template coffee shop untuk reset dan seed 132 menu kopi, non-kopi, cemilan, paket hemat, makanan utama, dan dessert.', 'examples' => 'Espresso, Americano, Latte, Iced Coffee, Croissant, Paket Hemat'],
        ['slug' => 'bakery-template', 'name' => 'Bakery Template', 'description' => 'Aktifkan plugin template bakery untuk seed 70 menu toko roti dan pastry.', 'examples' => 'Roti Tawar, Croissant, Donut, Cake Slice, Pastry, Paket Sarapan'],
        ['slug' => 'fruit-template', 'name' => 'Fruit Store Template', 'description' => 'Aktifkan plugin template toko buah untuk seed 60 produk buah, jus, smoothie, dan salad.', 'examples' => 'Apel, Jeruk, Pisang, Alpukat, Jus Mangga, Salad Buah'],
        ['slug' => 'meat-veggie-template', 'name' => 'Meat & Veggie Template', 'description' => 'Aktifkan plugin template fresh market untuk seed 80 produk daging dan sayuran.', 'examples' => 'Daging Sapi, Ayam Fillet, Ikan, Brokoli, Wortel, Bayam'],
        ['slug' => 'pharmacy-template', 'name' => 'Pharmacy / Apotek Template', 'description' => 'Aktifkan plugin template apotek untuk seed 120 produk lengkap dengan kategori obat, vitamin, alat kesehatan, varian kemasan, dan harga IDR.', 'examples' => 'Paracetamol, Vitamin C, Masker, Tensimeter, Obat Batuk, Suplemen'],
        ['slug' => 'minimarket-template', 'name' => 'Minimarket Template', 'description' => 'Aktifkan plugin template minimarket untuk seed 120 produk retail sehari-hari dengan berbagai kategori kebutuhan pokok.', 'examples' => 'Beras, Minyak Goreng, Indomie, Sabun, Shampo, Minuman Kemasan'],
        ['slug' => 'indonesian-resto-template', 'name' => 'Resto Indonesia Template', 'description' => 'Aktifkan plugin template resto masakan Indonesia untuk seed 125 menu tradisional dengan varian bumbu dan ukuran porsi.', 'examples' => 'Nasi Goreng, Soto Ayam, Rendang, Gado-Gado, Ayam Bakar, Es Teh'],
        ['slug' => 'warung-template', 'name' => 'Warung Makan Template', 'description' => 'Aktifkan plugin template warung makan untuk seed 15 menu khas warung Indonesia: nasi, lauk pauk, dan minuman warung.', 'examples' => 'Nasi Goreng, Ayam Goreng, Tempe, Tahu, Es Teh Manis, Kopi Tubruk'],
        ['slug' => 'resto-baso-template', 'name' => 'Resto Baso & Minuman Template', 'description' => 'Aktifkan plugin template resto baso untuk seed 15 menu bakso, mie, camilan, dan minuman segar khas warung baso.', 'examples' => 'Bakso Biasa, Bakso Urat, Bakso Telur, Mie Spesial, Pangsit Goreng, Es Campur'],
        ['slug' => 'kebab-template', 'name' => 'Kebab Template', 'description' => 'Aktifkan plugin template kedai kebab untuk seed 15 menu kebab sapi, ayam, mozarella, shawarma, falafel, dan minuman.', 'examples' => 'Kebab Original, Kebab Mozarella, Shawarma Ayam, Pita Falafel, Milkshake'],
        ['slug' => 'burger-template', 'name' => 'Burger Template', 'description' => 'Aktifkan plugin template kedai burger untuk seed 15 menu burger beef, ayam crispy, BBQ, fish fillet, sides, dan minuman.', 'examples' => 'Burger Classic Beef, Burger BBQ Smoky, French Fries, Onion Ring, Milkshake'],
        ['slug' => 'hp-accessories-template', 'name' => 'Toko Aksesori & Casing HP Template', 'description' => 'Aktifkan plugin template toko aksesori HP untuk seed 80 produk: casing, tempered glass, kabel, charger, earphone, power bank, holder, dan aksesori gaming.', 'examples' => 'Soft Case, Tempered Glass, Charger 33W, TWS Earbuds, Power Bank, Ring Stand'],
        ['slug' => 'fashion-wanita-template', 'name' => 'Toko Baju Busana Wanita Template', 'description' => 'Aktifkan plugin template toko fashion wanita untuk seed 80 produk: atasan, bawahan, dress, outer, gamis, casual, formal, dan aksesori fashion.', 'examples' => 'Blouse Rayon, Jeans Skinny, Midi Dress, Blazer, Gamis Syari, Tas Tote Bag'],
        ['slug' => 'tours-travel-template', 'name' => 'Tours & Travel Template', 'description' => 'Aktifkan plugin template jasa wisata dan travel untuk seed 15 layanan: tour domestik, internasional, visa, transfer, dan private guide.', 'examples' => 'Bali 3D2N, Labuan Bajo, Singapore, Visa Wisata, Airport Transfer'],
        ['slug' => 'umrah-template', 'name' => 'Umrah Template', 'description' => 'Aktifkan plugin template jasa umrah untuk seed 15 layanan: paket reguler, premium, plus tour, handling dokumen, dan perlengkapan jamaah.', 'examples' => 'Umrah 9 Hari, Umrah VIP, Plus Turki, Dokumen, Perlengkapan Umrah'],
    ];
}

function getCatalogTemplateInfo(string $slug): array
{
    foreach (getCatalogTemplateOptions() as $option) {
        if ($option['slug'] === $slug) {
            return $option;
        }
    }
    return getCatalogTemplateOptions()[0];
}

function discoverPlugins(): array
{
    $pluginsDir = ROOT . '/plugins';
    if (!is_dir($pluginsDir)) {
        return [];
    }

    bootstrapInstallerComposerAutoload();

    $defaults = array_flip(getDefaultPluginSelection());
    $dirs = array_filter(scandir($pluginsDir) ?: [], static function (string $name) use ($pluginsDir): bool {
        return $name !== '.' && $name !== '..' && is_dir($pluginsDir . '/' . $name);
    });
    $plugins = [];
    foreach ($dirs as $slug) {
        $entryFile = $pluginsDir . '/' . $slug . '/plugin.php';
        $name = ucwords(str_replace('-', ' ', $slug));
        $description = 'Plugin tambahan untuk fitur aplikasi.';
        if (file_exists($entryFile)) {
            $content = (string)file_get_contents($entryFile);
            if (preg_match("/'name'\s*=>\s*'([^']+)'/u", $content, $m)) {
                $name = $m[1];
            }
            if (preg_match("/'description'\s*=>\s*'([^']*)'/u", $content, $m) && $m[1] !== '') {
                $description = $m[1];
            }

            if ($name === ucwords(str_replace('-', ' ', $slug)) || $description === 'Plugin tambahan untuk fitur aplikasi.') {
                $metadata = extractPluginMetadataFromDirectory($pluginsDir . '/' . $slug);
                if (!empty($metadata['name'])) {
                    $name = $metadata['name'];
                }
                if (!empty($metadata['description'])) {
                    $description = $metadata['description'];
                }
            }
        }
        $plugins[] = ['slug' => $slug, 'name' => $name, 'description' => $description, 'active' => isset($defaults[$slug])];
    }
    usort($plugins, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $plugins;
}

function extractPluginMetadataFromDirectory(string $pluginDir): array
{
    $metadata = [
        'name' => null,
        'description' => null,
    ];

    foreach (glob($pluginDir . '/*.php') ?: [] as $file) {
        $content = (string)file_get_contents($file);

        if ($metadata['name'] === null
            && preg_match('/function\s+getName\s*\(\)\s*:\s*string\s*\{[^\}]*return\s+[\'"]([^\'"]+)[\'"]/su', $content, $match)
        ) {
            $metadata['name'] = $match[1];
        }

        if ($metadata['description'] === null
            && preg_match('/function\s+getDescription\s*\(\)\s*:\s*string\s*\{[^\}]*return\s+[\'"]([^\'"]+)[\'"]/su', $content, $match)
        ) {
            $metadata['description'] = $match[1];
        }

        if ($metadata['name'] !== null && $metadata['description'] !== null) {
            break;
        }
    }

    return $metadata;
}

function getDefaultPluginSelection(): array
{
    $configFile = ROOT . '/plugins/plugins.json';
    if (!file_exists($configFile)) {
        return [];
    }
    $json = json_decode((string)file_get_contents($configFile), true);
    if (!is_array($json)) {
        return [];
    }
    return array_keys(array_filter($json, static fn($cfg): bool => ($cfg['active'] ?? false) === true));
}

function writePluginsConfig(array $selectedSlugs): bool
{
    $plugins = discoverPlugins();
    $selectedMap = array_flip($selectedSlugs);
    $payload = [];
    foreach ($plugins as $plugin) {
        $payload[$plugin['slug']] = ['active' => isset($selectedMap[$plugin['slug']])];
    }
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents(ROOT . '/plugins/plugins.json', $json . PHP_EOL) !== false;
}

function renderShell(string $title, string $content): string
{
    $step = max(1, min(6, (int)($_GET['step'] ?? 1)));
    $done = isset($_GET['done']);
    $progress = '';
    if (!$done) {
        $steps = ['Persyaratan', 'Database', 'Aplikasi', 'Admin', 'Produk & Plugin', 'Instal'];
        $bars = '';
        foreach ($steps as $i => $label) {
            $n = $i + 1;
            if ($n < $step) {
                $cls = 'done';
            } elseif ($n === $step) {
                $cls = 'active';
            } else {
                $cls = '';
            }
            $bars .= "<div class=\"step {$cls}\"><span>{$n}</span>{$label}</div>";
        }
        $pct = min(100, (int)(($step - 1) / 5 * 100));
        $progress = "<div class=\"progress-wrap\"><div class=\"steps\">{$bars}</div><div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width:{$pct}%\"></div></div></div>";
    }
    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer — {$title}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f0eb;color:#333;min-height:100vh}.wrap{max-width:760px;margin:0 auto;padding:24px 16px}header{text-align:center;padding:28px 0 8px}header h1{color:#6f4e37;font-size:1.8rem}header p{color:#8b6f47;margin-top:4px}.progress-wrap{background:#fff;border-radius:12px;padding:20px;margin:16px 0;box-shadow:0 1px 4px rgba(0,0,0,.08)}.steps{display:flex;justify-content:space-between;margin-bottom:12px}.step{display:flex;flex-direction:column;align-items:center;gap:4px;font-size:.72rem;color:#aaa;flex:1;text-align:center}.step span{width:28px;height:28px;border-radius:50%;background:#e0d4c8;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}.step.done{color:#6f4e37}.step.done span{background:#6f4e37;color:#fff}.step.active{color:#a0522d}.step.active span{background:#a0522d;color:#fff}.progress-bar{background:#e0d4c8;border-radius:8px;height:6px}.progress-fill{background:#a0522d;border-radius:8px;height:6px;transition:width .4s}.card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:16px}.card h2{color:#6f4e37;margin-bottom:16px;font-size:1.2rem}.form-group{margin-bottom:16px}.form-group label{display:block;font-weight:600;margin-bottom:5px;font-size:.875rem}.form-group small{color:#888;font-size:.78rem}.form-control{width:100%;padding:8px 12px;border:1px solid #d0b89a;border-radius:8px;font-size:.9rem;outline:none}.form-control:focus{border-color:#a0522d;box-shadow:0 0 0 3px rgba(160,82,45,.12)}.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.form-nav{display:flex;justify-content:space-between;align-items:center;margin-top:20px}.btn{display:inline-block;padding:10px 22px;background:#a0522d;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none}.btn:hover{background:#8b4513}.btn-secondary{background:#e0d4c8;color:#6f4e37}.btn-secondary:hover{background:#cfc0ae}.btn-success{background:#3a7d44}.btn-success:hover{background:#2d6234}.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.875rem;line-height:1.55}.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}.alert-warning{background:#fff3cd;color:#856404;border:1px solid #ffeeba}.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.req-table,.summary-table{width:100%;border-collapse:collapse;font-size:.875rem}.req-table td,.summary-table td,.summary-table th{padding:7px 8px;border-bottom:1px solid #f0e8e0;text-align:left;vertical-align:top}.summary-table th{width:150px;color:#6f4e37}.req-table .pass td:first-child{color:#3a7d44}.req-table .fail td:first-child{color:#b00020}code{background:#f0e8e0;padding:2px 5px;border-radius:4px;font-size:.85em}.text-danger{color:#b00020}@media(max-width:640px){.form-row{grid-template-columns:1fr}.steps{gap:4px}.step{font-size:.62rem}.card{padding:20px}.form-nav{flex-direction:column;gap:10px;align-items:stretch}.btn{text-align:center}}
</style>
</head>
<body>
<div class="wrap">
<header><h1>AI Agent Commerce Installer</h1><p>Setup otomatis untuk coffee shop, restoran, bakery, pharmacy, toko buah, fresh market, mini mart, dan retail</p></header>
{$progress}
{$content}
</div>
</body>
</html>
HTML;
}
