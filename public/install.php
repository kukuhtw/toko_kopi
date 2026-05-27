<?php

declare(strict_types=1);

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
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            $errors[] = 'Nama database hanya boleh berisi huruf, angka, dan underscore.';
        } else {
            try {
                $testPdo = new PDO(
                    "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                $testPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
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
        $appName = trim($_POST['app_name'] ?? 'AI Agent Commerce');
        $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
        $appEnv = in_array($_POST['app_env'] ?? 'production', ['development', 'production'], true) ? $_POST['app_env'] : 'production';

        if ($appName === '' || $baseUrl === '') {
            $errors[] = 'Nama aplikasi dan Base URL wajib diisi.';
            $step = 3;
        } else {
            $_SESSION['app'] = compact('appName', 'baseUrl', 'appEnv');
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
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['dbName']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
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

    $schemaFile = DB_DIR . '/schema.sql';
    if (!file_exists($schemaFile)) {
        return ['success' => false, 'errors' => ['File database/schema.sql tidak ditemukan.']];
    }

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

    try {
        $hash = password_hash($admin['adminPass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE role = ? LIMIT 1')
            ->execute([$admin['adminName'], $admin['adminEmail'], $hash, 'super_admin']);
    } catch (PDOException $e) {
        $errors[] = 'Update admin gagal: ' . $e->getMessage();
    }

    if (file_put_contents(ROOT . '/.env', buildEnvContent($db, $app)) === false) {
        $errors[] = 'Gagal menulis file .env. Pastikan folder root dapat ditulis.';
    }

    if (!writePluginsConfig($plugins)) {
        $errors[] = 'Gagal menulis file plugins/plugins.json.';
    }

    @mkdir(ROOT . '/storage', 0755, true);
    @mkdir(ROOT . '/storage/logs', 0755, true);
    @mkdir(ROOT . '/uploads', 0755, true);

    if (file_put_contents(LOCK_FILE, date('Y-m-d H:i:s')) === false) {
        $errors[] = 'Gagal membuat storage/installed.lock. Folder storage mungkin tidak writable.';
    }

    return empty($errors) ? ['success' => true, 'errors' => []] : ['success' => false, 'errors' => $errors];
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
    $sql = file_get_contents($filePath);
    $stmts = splitSql((string)$sql);
    $errors = [];

    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $code = (int) $e->getCode();
            if (!in_array($code, [1050, 1060, 1061, 1062, 1068, 1071, 1091, 1170], true)) {
                $errors[] = substr($stmt, 0, 80) . '… → ' . $e->getMessage();
            }
        }
    }

    return $errors;
}

function splitSql(string $sql): array
{
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', (string)$sql);
    $statements = [];
    $current = '';
    $len = strlen((string)$sql);
    $inStr = false;
    $strChar = '';

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inStr) {
            $current .= $c;
            if ($c === $strChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inStr = false;
            }
        } elseif ($c === '"' || $c === "'" || $c === '`') {
            $inStr = true;
            $strChar = $c;
            $current .= $c;
        } elseif ($c === ';') {
            $s = trim($current);
            if ($s !== '') {
                $statements[] = $s;
            }
            $current = '';
        } else {
            $current .= $c;
        }
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
    return "<div class=\"card\"><h2>Langkah 1 — Persyaratan Sistem</h2><p>Installer ini dibuat untuk memudahkan setup AI Agent Commerce tanpa edit file manual.</p><table class=\"req-table\"><tbody>{$rows}</tbody></table><div style=\"margin-top:20px\">{$btn}</div></div>";
}

function renderStep2(array $errors): string
{
    $saved = $_SESSION['db'] ?? [];
    $err = renderErrors($errors);
    $v = fn(string $k, string $d) => htmlspecialchars((string)($saved[$k] ?? $d));
    return "<div class=\"card\"><h2>Langkah 2 — Konfigurasi Database</h2>{$err}<div class=\"alert alert-success\">Installer akan melakukan <strong>test koneksi</strong> lalu menjalankan <code>CREATE DATABASE IF NOT EXISTS</code>. Jadi database dapat dibuat otomatis selama user MySQL punya hak akses create database.</div><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step2_save\"><div class=\"form-row\"><div class=\"form-group\"><label>Host Database</label><input type=\"text\" name=\"db_host\" class=\"form-control\" value=\"{$v('dbHost', 'localhost')}\" required></div><div class=\"form-group\" style=\"max-width:120px\"><label>Port</label><input type=\"number\" name=\"db_port\" class=\"form-control\" value=\"{$v('dbPort', '3306')}\" required></div></div><div class=\"form-group\"><label>Nama Database</label><input type=\"text\" name=\"db_name\" class=\"form-control\" value=\"{$v('dbName', 'toko_kopi')}\" required><small>Contoh: <code>toko_kopi</code>, <code>ai_commerce_pharmacy</code>, <code>ai_commerce_mart</code>. Hanya huruf, angka, underscore.</small></div><div class=\"form-row\"><div class=\"form-group\"><label>User Database</label><input type=\"text\" name=\"db_user\" class=\"form-control\" value=\"{$v('dbUser', 'root')}\" required></div><div class=\"form-group\"><label>Password Database</label><input type=\"password\" name=\"db_pass\" class=\"form-control\" value=\"\" placeholder=\"Kosongkan jika tidak ada\"></div></div><div class=\"form-nav\"><a href=\"install.php?step=1\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Test, Create DB &amp; Lanjut &rarr;</button></div></form></div>";
}

function renderStep3(array $errors): string
{
    $saved = $_SESSION['app'] ?? [];
    $err = renderErrors($errors);
    $autoUrl = detectBaseUrl();
    $appName = htmlspecialchars((string)($saved['appName'] ?? 'AI Agent Commerce'));
    $baseUrl = htmlspecialchars((string)($saved['baseUrl'] ?? $autoUrl));
    $devSel = ($saved['appEnv'] ?? 'production') === 'development' ? 'selected' : '';
    $prodSel = ($saved['appEnv'] ?? 'production') === 'production' ? 'selected' : '';
    return "<div class=\"card\"><h2>Langkah 3 — Pengaturan Aplikasi</h2>{$err}<form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step3_save\"><div class=\"form-group\"><label>Nama Brand / Toko</label><input type=\"text\" name=\"app_name\" class=\"form-control\" value=\"{$appName}\" required placeholder=\"AI Commerce Mart\"><small>Contoh: KopiBot Cafe, Fresh Mart AI, Pharmacy Agent, Bakery Commerce.</small></div><div class=\"form-group\"><label>Base URL</label><input type=\"url\" name=\"base_url\" class=\"form-control\" value=\"{$baseUrl}\" required><small>URL lengkap ke folder <code>public/</code>. Contoh: <code>http://localhost/toko_kopi/public</code></small></div><div class=\"form-group\"><label>Lingkungan</label><select name=\"app_env\" class=\"form-control\"><option value=\"production\" {$prodSel}>Production</option><option value=\"development\" {$devSel}>Development (tampilkan error)</option></select></div><div class=\"form-nav\"><a href=\"install.php?step=2\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Lanjut &rarr;</button></div></form></div>";
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
        $templateCards .= '<label style="display:block;border:1px solid #e0d4c8;border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;background:#fff"><div style="display:flex;gap:12px"><input type="radio" name="catalog_template" value="' . htmlspecialchars($option['slug']) . '" ' . $checked . ' style="margin-top:3px"><div><div style="font-weight:700;color:#6f4e37">' . htmlspecialchars($option['name']) . '</div><div style="font-size:.82rem;color:#8b6f47;margin-top:4px">' . htmlspecialchars($option['description']) . '</div><div style="font-size:.78rem;color:#a08a72;margin-top:6px">Contoh produk: ' . htmlspecialchars($option['examples']) . '</div></div></div></label>';
    }
    $cards = '';
    foreach ($plugins as $plugin) {
        $checked = in_array($plugin['slug'], $selected, true) ? 'checked' : '';
        $cards .= '<label style="display:block;border:1px solid #e0d4c8;border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;background:#fffaf6"><div style="display:flex;align-items:flex-start;gap:12px"><input type="checkbox" name="plugins[]" value="' . htmlspecialchars($plugin['slug']) . '" ' . $checked . ' style="margin-top:3px"><div><div style="font-weight:700;color:#6f4e37">' . htmlspecialchars($plugin['name']) . '</div><div style="font-size:.82rem;color:#8b6f47;margin-top:4px">' . htmlspecialchars($plugin['description']) . '</div><div style="font-size:.75rem;color:#a08a72;margin-top:6px"><code>' . htmlspecialchars($plugin['slug']) . '</code></div></div></div></label>';
    }
    if ($cards === '') {
        $cards = '<div class="alert alert-warning">Belum ada plugin yang ditemukan di folder <code>plugins/</code>.</div>';
    }
    return "<div class=\"card\"><h2>Langkah 5 — Pilih Contoh Data Produk & Plugin</h2>{$err}<p>Pilih contoh data produk/menu yang paling mendekati jenis bisnis. Template menu yang sudah ada akan diaktifkan sebagai plugin agar admin tahu dataset mana yang dipakai.</p><h3 style=\"color:#6f4e37;margin:18px 0 10px\">Contoh Data Produk / Menu</h3><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"step5_save_plugins\">{$templateCards}<h3 style=\"color:#6f4e37;margin:22px 0 10px\">Plugin Aktif</h3><p style=\"font-size:.88rem;color:#8b6f47\">Payment, channel, CRM, FAQ, POS, delivery, dan fitur lain dapat dipilih sesuai kebutuhan.</p><div style=\"margin-top:18px\">{$cards}</div><div class=\"form-nav\"><a href=\"install.php?step=4\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn\">Lanjut &rarr;</button></div></form></div>";
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
    return "<div class=\"card\"><h2>Langkah 6 — Jalankan Instalasi</h2>{$err}<table class=\"summary-table\"><tr><th>Database</th><td>{$dbSummary}</td></tr><tr><th>Base URL</th><td>" . htmlspecialchars($app['baseUrl'] ?? '') . "</td></tr><tr><th>Lingkungan</th><td>" . htmlspecialchars($app['appEnv'] ?? '') . "</td></tr><tr><th>Nama Toko</th><td>" . htmlspecialchars($app['appName'] ?? '') . "</td></tr><tr><th>Admin Email</th><td>" . htmlspecialchars($admin['adminEmail'] ?? '') . "</td></tr><tr><th>Template Produk</th><td>" . htmlspecialchars($templateInfo['name']) . "<br><small>" . htmlspecialchars($templateInfo['examples']) . "</small></td></tr><tr><th>Plugin Aktif</th><td>" . htmlspecialchars(implode(', ', $plugins ?: ['Tidak ada'])) . "</td></tr></table><p>Proses ini akan:</p><ul><li>Membuat database otomatis bila belum ada</li><li>Membuat semua tabel dari <code>database/schema.sql</code></li><li>Mengisi data awal dari <code>database/seed.sql</code></li><li>Mencatat pilihan template produk/menu agar plugin template terkait aktif</li><li>Membuat akun super admin</li><li>Menulis file <code>.env</code></li><li>Menulis file <code>plugins/plugins.json</code></li><li>Membuat <code>storage/installed.lock</code></li></ul><div class=\"alert alert-warning\"><strong>Catatan:</strong> Plugin template seperti coffee, bakery, fruit, meat & veggie saat ini berfungsi sebagai template reset/seed yang dapat dijalankan dari dashboard/plugin flow. Installer memilih dan mengaktifkan plugin yang sesuai agar bisnis vertical langsung jelas setelah instalasi.</div><form method=\"POST\"><input type=\"hidden\" name=\"action\" value=\"run_install\"><div class=\"form-nav\"><a href=\"install.php?step=5\" class=\"btn btn-secondary\">&larr; Kembali</a><button type=\"submit\" class=\"btn btn-success\">&#9889; Jalankan Instalasi</button></div></form></div>";
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
    return preg_replace('/[^A-Za-z0-9_]/', '_', $name) ?: 'toko_kopi';
}

function getCatalogTemplateOptions(): array
{
    return [
        ['slug' => 'keep-seed', 'name' => 'Default Seed Coffee Menu', 'description' => 'Gunakan data awal dari database/seed.sql. Cocok untuk demo cepat karena seed saat ini berisi contoh menu kopi lengkap.', 'examples' => 'Espresso, Americano, Cappuccino, Latte, Flat White, Kopi Tubruk'],
        ['slug' => 'coffee-template', 'name' => 'Coffee Shop Template', 'description' => 'Aktifkan plugin template coffee shop untuk reset dan seed 132 menu kopi, non-kopi, cemilan, paket hemat, makanan utama, dan dessert.', 'examples' => 'Espresso, Americano, Latte, Iced Coffee, Croissant, Paket Hemat'],
        ['slug' => 'bakery-template', 'name' => 'Bakery Template', 'description' => 'Aktifkan plugin template bakery untuk seed 70 menu toko roti dan pastry.', 'examples' => 'Roti Tawar, Croissant, Donut, Cake Slice, Pastry, Paket Sarapan'],
        ['slug' => 'fruit-template', 'name' => 'Fruit Store Template', 'description' => 'Aktifkan plugin template toko buah untuk seed 60 produk buah, jus, smoothie, dan salad.', 'examples' => 'Apel, Jeruk, Pisang, Alpukat, Jus Mangga, Salad Buah'],
        ['slug' => 'meat-veggie-template', 'name' => 'Meat & Veggie Template', 'description' => 'Aktifkan plugin template fresh market untuk seed 80 produk daging dan sayuran.', 'examples' => 'Daging Sapi, Ayam Fillet, Ikan, Brokoli, Wortel, Bayam'],
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
        }
        $plugins[] = ['slug' => $slug, 'name' => $name, 'description' => $description, 'active' => isset($defaults[$slug])];
    }
    usort($plugins, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $plugins;
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
            $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
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
<header><h1>AI Agent Commerce Installer</h1><p>Setup mudah untuk kuliner, pharmacy, mart, fresh market, dan retail commerce</p></header>
{$progress}
{$content}
</div>
</body>
</html>
HTML;
}
