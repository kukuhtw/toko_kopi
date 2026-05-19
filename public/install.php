<?php

declare(strict_types=1);

// ── Lock-file check ──────────────────────────────────────────────────────────
define('ROOT',      dirname(__DIR__));
define('LOCK_FILE', ROOT . '/storage/installed.lock');
define('DB_DIR',    ROOT . '/database');

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

// ── Session ──────────────────────────────────────────────────────────────────
session_name('toko_kopi_installer');
session_start();

$step   = max(1, min(6, (int) ($_GET['step'] ?? 1)));
$errors = [];

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // Step 1 → 2: requirements were checked client-side; just proceed
    if ($action === 'step1_next') {
        header('Location: install.php?step=2');
        exit;
    }

    // Step 2: save DB config
    if ($action === 'step2_save') {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'toko_kopi');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            $errors[] = 'Host, nama database, dan user wajib diisi.';
        } else {
            try {
                // Connect without a database to test credentials
                $testPdo = new PDO(
                    "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                    $dbUser, $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                unset($testPdo);

                $_SESSION['db'] = compact('dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass');
                header('Location: install.php?step=3');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Koneksi database gagal: ' . $e->getMessage();
            }
        }
        $step = 2;
    }

    // Step 3: save app config
    if ($action === 'step3_save') {
        $appName = trim($_POST['app_name'] ?? 'Toko Kopi');
        $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
        $appEnv  = in_array($_POST['app_env'] ?? 'production', ['development','production'])
                    ? $_POST['app_env'] : 'production';

        if ($appName === '' || $baseUrl === '') {
            $errors[] = 'Nama aplikasi dan Base URL wajib diisi.';
            $step = 3;
        } else {
            $_SESSION['app'] = compact('appName', 'baseUrl', 'appEnv');
            header('Location: install.php?step=4');
            exit;
        }
    }

    // Step 4: save admin account
    if ($action === 'step4_save') {
        $adminName  = trim($_POST['admin_name']  ?? 'Super Admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass  = $_POST['admin_pass']  ?? '';
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

    // Step 5: save plugin selection
    if ($action === 'step5_save_plugins') {
        $available = array_column(discoverPlugins(), 'slug');
        $selected  = array_values(array_intersect($available, (array)($_POST['plugins'] ?? [])));
        $_SESSION['plugins'] = $selected;
        header('Location: install.php?step=6');
        exit;
    }

    // Step 6: run installation
    if ($action === 'run_install') {
        $result = runInstallation();
        if ($result['success']) {
            header('Location: install.php?done=1');
            exit;
        }
        $errors = $result['errors'];
        $step   = 6;
    }
}

// ── Done page ─────────────────────────────────────────────────────────────────
if (isset($_GET['done'])) {
    $admin   = $_SESSION['admin'] ?? [];
    $baseUrl = $_SESSION['app']['baseUrl'] ?? '';
    session_destroy();
    echo renderShell('Instalasi Berhasil', '
        <div class="card" style="text-align:center">
            <div style="font-size:4rem">☕</div>
            <h2 style="color:#6f4e37;margin:12px 0">Instalasi Berhasil!</h2>
            <p>Aplikasi Toko Kopi siap digunakan.</p>
            <div class="alert alert-success" style="text-align:left">
                <strong>Akun Super Admin:</strong><br>
                Email: <code>' . htmlspecialchars($admin['adminEmail'] ?? '-') . '</code><br>
                Password: <em>yang kamu masukkan tadi</em>
            </div>
            <div class="alert alert-warning" style="text-align:left">
                ⚠️ <strong>Penting:</strong> Segera hapus atau rename file <code>public/install.php</code>
                dari server untuk keamanan.
            </div>
            <a class="btn" href="login.php">Login ke Dashboard</a>
        </div>');
    exit;
}

// ── Render step ───────────────────────────────────────────────────────────────
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

// ═════════════════════════════════════════════════════════════════════════════
// INSTALLATION LOGIC
// ═════════════════════════════════════════════════════════════════════════════

function runInstallation(): array
{
    $errors = [];

    $db    = $_SESSION['db']    ?? [];
    $app   = $_SESSION['app']   ?? [];
    $admin = $_SESSION['admin'] ?? [];
    $plugins = $_SESSION['plugins'] ?? getDefaultPluginSelection();

    if (empty($db) || empty($app) || empty($admin)) {
        return ['success' => false, 'errors' => ['Data sesi tidak lengkap. Mulai dari langkah 1.']];
    }

    // 1. Connect without database
    try {
        $pdo = new PDO(
            "mysql:host={$db['dbHost']};port={$db['dbPort']};charset=utf8mb4",
            $db['dbUser'], $db['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Koneksi DB gagal: ' . $e->getMessage()]];
    }

    // 2. Run schema.sql (creates DB + all tables)
    $schemaFile = DB_DIR . '/schema.sql';
    if (!file_exists($schemaFile)) {
        return ['success' => false, 'errors' => ['File database/schema.sql tidak ditemukan.']];
    }

    $schemaErrors = executeSqlFile($pdo, $schemaFile);
    if ($schemaErrors) {
        $errors = array_merge($errors, array_map(fn($e) => "[schema] $e", $schemaErrors));
    }

    // 3. Connect to the target database
    try {
        $pdo = new PDO(
            "mysql:host={$db['dbHost']};port={$db['dbPort']};dbname={$db['dbName']};charset=utf8mb4",
            $db['dbUser'], $db['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Koneksi ke database gagal: ' . $e->getMessage()]];
    }

    // 4. Run seed.sql
    $seedFile = DB_DIR . '/seed.sql';
    if (file_exists($seedFile)) {
        $seedErrors = executeSqlFile($pdo, $seedFile);
        if ($seedErrors) {
            $errors = array_merge($errors, array_map(fn($e) => "[seed] $e", $seedErrors));
        }
    }

    // 5. Update super admin with user-supplied credentials
    try {
        $hash = password_hash($admin['adminPass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare(
            'UPDATE users SET name = ?, email = ?, password = ? WHERE role = ? LIMIT 1'
        )->execute([$admin['adminName'], $admin['adminEmail'], $hash, 'super_admin']);
    } catch (PDOException $e) {
        $errors[] = 'Update admin gagal: ' . $e->getMessage();
    }

    // 6. Write .env
    $envPath = ROOT . '/.env';
    $envContent = buildEnvContent($db, $app);
    if (file_put_contents($envPath, $envContent) === false) {
        $errors[] = 'Gagal menulis file .env. Pastikan folder root dapat ditulis.';
    }

    // 7. Write plugins/plugins.json
    if (!writePluginsConfig($plugins)) {
        $errors[] = 'Gagal menulis file plugins/plugins.json.';
    }

    // 8. Create storage dirs and lock file
    @mkdir(ROOT . '/storage',       0755, true);
    @mkdir(ROOT . '/storage/logs',  0755, true);
    @mkdir(ROOT . '/uploads',       0755, true);

    if (file_put_contents(LOCK_FILE, date('Y-m-d H:i:s')) === false) {
        $errors[] = 'Gagal membuat storage/installed.lock. Folder storage mungkin tidak writable.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    return ['success' => true, 'errors' => []];
}

function buildEnvContent(array $db, array $app): string
{
    return <<<ENV
# ============================================================
# Toko Kopi — Generated by Web Installer {$app['appName']}
# ============================================================

# ── Database ─────────────────────────────────────────────────
DB_HOST={$db['dbHost']}
DB_PORT={$db['dbPort']}
DB_NAME={$db['dbName']}
DB_USER={$db['dbUser']}
DB_PASS={$db['dbPass']}

# ── Aplikasi ─────────────────────────────────────────────────
APP_ENV={$app['appEnv']}
BASE_URL={$app['baseUrl']}

# ── LLM Providers ────────────────────────────────────────────
# Opsional — bisa juga diisi lewat Dashboard → Settings → API Key
ANTHROPIC_API_KEY=
OPENROUTER_API_KEY=
ENV;
}

/** Split a .sql file into individual statements and execute each one. */
function executeSqlFile(PDO $pdo, string $filePath): array
{
    $sql      = file_get_contents($filePath);
    $stmts    = splitSql($sql);
    $errors   = [];

    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Ignore "already exists" and "already defined" warnings
            $code = (int) $e->getCode();
            if (!in_array($code, [1050, 1060, 1061, 1062, 1068, 1071, 1091, 1170])) {
                $errors[] = substr($stmt, 0, 80) . '… → ' . $e->getMessage();
            }
        }
    }

    return $errors;
}

/** Character-by-character SQL splitter — handles string literals correctly. */
function splitSql(string $sql): array
{
    // Strip single-line comments
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    // Strip multi-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $inStr      = false;
    $strChar    = '';

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($inStr) {
            $current .= $c;
            if ($c === $strChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inStr = false;
            }
        } elseif ($c === '"' || $c === "'" || $c === '`') {
            $inStr   = true;
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

// ═════════════════════════════════════════════════════════════════════════════
// STEP RENDERERS
// ═════════════════════════════════════════════════════════════════════════════

function renderStep1(): string
{
    $root = ROOT;

    $checks = [
        ['PHP >= 8.0',      version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
        ['PDO',             extension_loaded('pdo'),            ''],
        ['PDO MySQL',       extension_loaded('pdo_mysql'),      ''],
        ['mbstring',        extension_loaded('mbstring'),       ''],
        ['json',            extension_loaded('json'),           ''],
        ['curl',            extension_loaded('curl'),           ''],
        ['openssl',         extension_loaded('openssl'),        ''],
        ['storage/ writable',      is_writable("{$root}/storage")      || @mkdir("{$root}/storage",0755,true), ''],
        ['storage/logs/ writable', is_writable("{$root}/storage/logs") || @mkdir("{$root}/storage/logs",0755,true), ''],
        ['uploads/ writable',      is_writable("{$root}/uploads")      || @mkdir("{$root}/uploads",0755,true), ''],
        ['.env writable',          is_writable($root) || is_writable("{$root}/.env") || !file_exists("{$root}/.env"), ''],
    ];

    $allPass = array_reduce($checks, fn($carry, $c) => $carry && $c[1], true);
    $rows    = '';

    foreach ($checks as [$label, $pass, $detail]) {
        $icon  = $pass ? '✅' : '❌';
        $class = $pass ? 'pass' : 'fail';
        $rows .= "<tr class=\"{$class}\"><td>{$icon}</td><td>{$label}</td><td>" .
                 htmlspecialchars($detail) . "</td></tr>";
    }

    $btn = $allPass
        ? '<form method="POST"><input type="hidden" name="action" value="step1_next">
           <button type="submit" class="btn">Lanjut ke Langkah 2 &rarr;</button></form>'
        : '<p class="text-danger">Perbaiki masalah di atas sebelum melanjutkan.</p>';

    return "
    <div class=\"card\">
        <h2>Langkah 1 — Persyaratan Sistem</h2>
        <table class=\"req-table\"><tbody>{$rows}</tbody></table>
        <div style=\"margin-top:20px\">{$btn}</div>
    </div>";
}

function renderStep2(array $errors): string
{
    $saved = $_SESSION['db'] ?? [];
    $err   = renderErrors($errors);

    $v = fn(string $k, string $d) => htmlspecialchars((string) ($saved[$k] ?? $d));

    return "
    <div class=\"card\">
        <h2>Langkah 2 — Konfigurasi Database</h2>
        {$err}
        <form method=\"POST\">
            <input type=\"hidden\" name=\"action\" value=\"step2_save\">
            <div class=\"form-row\">
                <div class=\"form-group\">
                    <label for=\"db_host\">Host Database</label>
                    <input id=\"db_host\" type=\"text\" name=\"db_host\" class=\"form-control\"
                           value=\"{$v('dbHost','localhost')}\" required placeholder=\"localhost\">
                </div>
                <div class=\"form-group\" style=\"max-width:120px\">
                    <label for=\"db_port\">Port</label>
                    <input id=\"db_port\" type=\"number\" name=\"db_port\" class=\"form-control\"
                           value=\"{$v('dbPort','3306')}\" required>
                </div>
            </div>
            <div class=\"form-group\">
                <label for=\"db_name\">Nama Database</label>
                <input id=\"db_name\" type=\"text\" name=\"db_name\" class=\"form-control\"
                       value=\"{$v('dbName','toko_kopi')}\" required>
                <small>Database akan dibuat otomatis jika belum ada.</small>
            </div>
            <div class=\"form-row\">
                <div class=\"form-group\">
                    <label for=\"db_user\">User Database</label>
                    <input id=\"db_user\" type=\"text\" name=\"db_user\" class=\"form-control\"
                           value=\"{$v('dbUser','root')}\" required>
                </div>
                <div class=\"form-group\">
                    <label for=\"db_pass\">Password Database</label>
                    <input id=\"db_pass\" type=\"password\" name=\"db_pass\" class=\"form-control\"
                           value=\"\" placeholder=\"Kosongkan jika tidak ada\">
                </div>
            </div>
            <div class=\"form-nav\">
                <a href=\"install.php?step=1\" class=\"btn btn-secondary\">&larr; Kembali</a>
                <button type=\"submit\" class=\"btn\">Test &amp; Lanjut &rarr;</button>
            </div>
        </form>
    </div>";
}

function renderStep3(array $errors): string
{
    $saved   = $_SESSION['app'] ?? [];
    $err     = renderErrors($errors);
    $autoUrl = detectBaseUrl();

    $appName = htmlspecialchars((string) ($saved['appName'] ?? 'Toko Kopi'));
    $baseUrl = htmlspecialchars((string) ($saved['baseUrl'] ?? $autoUrl));
    $devSel  = ($saved['appEnv'] ?? 'production') === 'development' ? 'selected' : '';
    $prodSel = ($saved['appEnv'] ?? 'production') === 'production'  ? 'selected' : '';

    return "
    <div class=\"card\">
        <h2>Langkah 3 — Pengaturan Aplikasi</h2>
        {$err}
        <form method=\"POST\">
            <input type=\"hidden\" name=\"action\" value=\"step3_save\">
            <div class=\"form-group\">
                <label for=\"app_name\">Nama Brand / Toko</label>
                <input id=\"app_name\" type=\"text\" name=\"app_name\" class=\"form-control\"
                       value=\"{$appName}\" required placeholder=\"Toko Kopi Saya\">
            </div>
            <div class=\"form-group\">
                <label for=\"base_url\">Base URL</label>
                <input id=\"base_url\" type=\"url\" name=\"base_url\" class=\"form-control\"
                       value=\"{$baseUrl}\" required>
                <small>URL lengkap ke folder <code>public/</code>. Contoh: <code>http://localhost/toko_kopi/public</code></small>
            </div>
            <div class=\"form-group\">
                <label for=\"app_env\">Lingkungan</label>
                <select id=\"app_env\" name=\"app_env\" class=\"form-control\">
                    <option value=\"production\"  {$prodSel}>Production</option>
                    <option value=\"development\" {$devSel}>Development (tampilkan error)</option>
                </select>
            </div>
            <div class=\"form-nav\">
                <a href=\"install.php?step=2\" class=\"btn btn-secondary\">&larr; Kembali</a>
                <button type=\"submit\" class=\"btn\">Lanjut &rarr;</button>
            </div>
        </form>
    </div>";
}

function renderStep4(array $errors): string
{
    $saved = $_SESSION['admin'] ?? [];
    $err   = renderErrors($errors);

    $name  = htmlspecialchars((string) ($saved['adminName']  ?? 'Super Admin'));
    $email = htmlspecialchars((string) ($saved['adminEmail'] ?? ''));

    return "
    <div class=\"card\">
        <h2>Langkah 4 — Akun Super Admin</h2>
        {$err}
        <p>Akun ini akan menjadi administrator utama dengan akses penuh.</p>
        <form method=\"POST\">
            <input type=\"hidden\" name=\"action\" value=\"step4_save\">
            <div class=\"form-group\">
                <label for=\"admin_name\">Nama</label>
                <input id=\"admin_name\" type=\"text\" name=\"admin_name\" class=\"form-control\"
                       value=\"{$name}\" required>
            </div>
            <div class=\"form-group\">
                <label for=\"admin_email\">Email</label>
                <input id=\"admin_email\" type=\"email\" name=\"admin_email\" class=\"form-control\"
                       value=\"{$email}\" required placeholder=\"admin@toko-kamu.com\">
            </div>
            <div class=\"form-row\">
                <div class=\"form-group\">
                    <label for=\"admin_pass\">Password <small>(min 8 karakter)</small></label>
                    <input id=\"admin_pass\" type=\"password\" name=\"admin_pass\" class=\"form-control\"
                           minlength=\"8\" required autocomplete=\"new-password\">
                </div>
                <div class=\"form-group\">
                    <label for=\"admin_pass2\">Konfirmasi Password</label>
                    <input id=\"admin_pass2\" type=\"password\" name=\"admin_pass2\" class=\"form-control\"
                           minlength=\"8\" required autocomplete=\"new-password\">
                </div>
            </div>
            <div class=\"form-nav\">
                <a href=\"install.php?step=3\" class=\"btn btn-secondary\">&larr; Kembali</a>
                <button type=\"submit\" class=\"btn\">Lanjut &rarr;</button>
            </div>
        </form>
    </div>";
}

function renderStep5(array $errors): string
{
    $err = renderErrors($errors);
    $plugins = discoverPlugins();
    $selected = $_SESSION['plugins'] ?? getDefaultPluginSelection();

    $cards = '';
    foreach ($plugins as $plugin) {
        $checked = in_array($plugin['slug'], $selected, true) ? 'checked' : '';
        $cards .= '
        <label style="display:block;border:1px solid #e0d4c8;border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;background:#fffaf6">
            <div style="display:flex;align-items:flex-start;gap:12px">
                <input type="checkbox" name="plugins[]" value="' . htmlspecialchars($plugin['slug']) . '" ' . $checked . ' style="margin-top:3px">
                <div>
                    <div style="font-weight:700;color:#6f4e37">' . htmlspecialchars($plugin['name']) . '</div>
                    <div style="font-size:.82rem;color:#8b6f47;margin-top:4px">' . htmlspecialchars($plugin['description']) . '</div>
                    <div style="font-size:.75rem;color:#a08a72;margin-top:6px"><code>' . htmlspecialchars($plugin['slug']) . '</code></div>
                </div>
            </div>
        </label>';
    }

    if ($cards === '') {
        $cards = '<div class="alert alert-warning">Belum ada plugin yang ditemukan di folder <code>plugins/</code>.</div>';
    }

    return "
    <div class=\"card\">
        <h2>Langkah 5 — Pilih Plugin</h2>
        {$err}
        <p>Pilih plugin yang ingin langsung aktif setelah instalasi selesai. Pengaturan ini akan ditulis ke <code>plugins/plugins.json</code>.</p>
        <form method=\"POST\">
            <input type=\"hidden\" name=\"action\" value=\"step5_save_plugins\">
            <div style=\"margin-top:18px\">{$cards}</div>
            <div class=\"form-nav\">
                <a href=\"install.php?step=4\" class=\"btn btn-secondary\">&larr; Kembali</a>
                <button type=\"submit\" class=\"btn\">Lanjut &rarr;</button>
            </div>
        </form>
    </div>";
}

function renderStep6(array $errors): string
{
    $db    = $_SESSION['db']    ?? [];
    $app   = $_SESSION['app']   ?? [];
    $admin   = $_SESSION['admin']   ?? [];
    $plugins = $_SESSION['plugins'] ?? getDefaultPluginSelection();
    $err     = renderErrors($errors);

    $dbSummary = sprintf(
        '%s:%s / %s (user: %s)',
        htmlspecialchars($db['dbHost'] ?? ''),
        htmlspecialchars($db['dbPort'] ?? ''),
        htmlspecialchars($db['dbName'] ?? ''),
        htmlspecialchars($db['dbUser'] ?? '')
    );

    return "
    <div class=\"card\">
        <h2>Langkah 6 — Jalankan Instalasi</h2>
        {$err}
        <table class=\"summary-table\">
            <tr><th>Database</th><td>{$dbSummary}</td></tr>
            <tr><th>Base URL</th><td>" . htmlspecialchars($app['baseUrl'] ?? '') . "</td></tr>
            <tr><th>Lingkungan</th><td>" . htmlspecialchars($app['appEnv'] ?? '') . "</td></tr>
            <tr><th>Nama Toko</th><td>" . htmlspecialchars($app['appName'] ?? '') . "</td></tr>
            <tr><th>Admin Email</th><td>" . htmlspecialchars($admin['adminEmail'] ?? '') . "</td></tr>
            <tr><th>Plugin Aktif</th><td>" . htmlspecialchars(implode(', ', $plugins ?: ['Tidak ada'])) . "</td></tr>
        </table>
        <p>Proses ini akan:</p>
        <ul>
            <li>Membuat database dan semua tabel</li>
            <li>Mengisi data awal (menu, cabang sampel, pengaturan default)</li>
            <li>Membuat akun super admin</li>
            <li>Menulis file <code>.env</code></li>
            <li>Menulis file <code>plugins/plugins.json</code> sesuai plugin terpilih</li>
            <li>Membuat <code>storage/installed.lock</code></li>
        </ul>
        <form method=\"POST\">
            <input type=\"hidden\" name=\"action\" value=\"run_install\">
            <div class=\"form-nav\">
                <a href=\"install.php?step=5\" class=\"btn btn-secondary\">&larr; Kembali</a>
                <button type=\"submit\" class=\"btn btn-success\">&#9889; Jalankan Instalasi</button>
            </div>
        </form>
    </div>";
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS
// ═════════════════════════════════════════════════════════════════════════════

function detectBaseUrl(): string
{
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri   = $_SERVER['REQUEST_URI'] ?? '/install.php';
    $dir   = rtrim(str_replace('/install.php', '', strtok($uri, '?')), '/');
    return $proto . '://' . $host . $dir;
}

function renderErrors(array $errors): string
{
    if (empty($errors)) return '';
    $items = implode('', array_map(fn($e) => '<li>' . htmlspecialchars($e) . '</li>', $errors));
    return "<div class=\"alert alert-error\"><ul style=\"margin:0;padding-left:18px\">{$items}</ul></div>";
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
            if (preg_match("/'name'\\s*=>\\s*'([^']+)'/u", $content, $m)) {
                $name = $m[1];
            }
            if (preg_match("/'description'\\s*=>\\s*'([^']*)'/u", $content, $m) && $m[1] !== '') {
                $description = $m[1];
            }
        }

        $plugins[] = [
            'slug'        => $slug,
            'name'        => $name,
            'description' => $description,
            'active'      => isset($defaults[$slug]),
        ];
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
        $payload[$plugin['slug']] = [
            'active' => isset($selectedMap[$plugin['slug']]),
        ];
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(ROOT . '/plugins/plugins.json', $json . PHP_EOL) !== false;
}

// ═════════════════════════════════════════════════════════════════════════════
// HTML SHELL
// ═════════════════════════════════════════════════════════════════════════════

function renderShell(string $title, string $content): string
{
    $step = max(1, min(6, (int) ($_GET['step'] ?? 1)));
    $done = isset($_GET['done']);

    $progress = '';
    if (!$done) {
        $steps = ['Persyaratan', 'Database', 'Aplikasi', 'Admin', 'Plugin', 'Instal'];
        $bars  = '';
        foreach ($steps as $i => $label) {
            $n    = $i + 1;
            $cls  = $n < $step ? 'done' : ($n === $step ? 'active' : '');
            $bars .= "<div class=\"step {$cls}\"><span>{$n}</span>{$label}</div>";
        }
        $pct      = min(100, (int) (($step - 1) / 5 * 100));
        $progress = "
        <div class=\"progress-wrap\">
            <div class=\"steps\">{$bars}</div>
            <div class=\"progress-bar\"><div class=\"progress-fill\" style=\"width:{$pct}%\"></div></div>
        </div>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer — {$title}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f0eb;color:#333;min-height:100vh}
.wrap{max-width:680px;margin:0 auto;padding:24px 16px}
header{text-align:center;padding:28px 0 8px}
header h1{color:#6f4e37;font-size:1.8rem}
header p{color:#8b6f47;margin-top:4px}
.progress-wrap{background:#fff;border-radius:12px;padding:20px;margin:16px 0;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.steps{display:flex;justify-content:space-between;margin-bottom:12px}
.step{display:flex;flex-direction:column;align-items:center;gap:4px;font-size:.75rem;color:#aaa;flex:1}
.step span{width:28px;height:28px;border-radius:50%;background:#e0d4c8;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}
.step.done{color:#6f4e37}.step.done span{background:#6f4e37;color:#fff}
.step.active{color:#a0522d}.step.active span{background:#a0522d;color:#fff}
.progress-bar{background:#e0d4c8;border-radius:8px;height:6px}
.progress-fill{background:#a0522d;border-radius:8px;height:6px;transition:width .4s}
.card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:16px}
.card h2{color:#6f4e37;margin-bottom:16px;font-size:1.2rem}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;font-size:.875rem}
.form-group small{color:#888;font-size:.78rem}
.form-control{width:100%;padding:8px 12px;border:1px solid #d0b89a;border-radius:8px;font-size:.9rem;outline:none}
.form-control:focus{border-color:#a0522d;box-shadow:0 0 0 3px rgba(160,82,45,.12)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-nav{display:flex;justify-content:space-between;align-items:center;margin-top:20px}
.btn{display:inline-block;padding:10px 22px;background:#a0522d;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none}
.btn:hover{background:#8b4513}
.btn-secondary{background:#e0d4c8;color:#6f4e37}.btn-secondary:hover{background:#cfc0ae}
.btn-success{background:#3a7d44}.btn-success:hover{background:#2d6234}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.875rem}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert-warning{background:#fff3cd;color:#856404;border:1px solid #ffeeba}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.req-table{width:100%;border-collapse:collapse;font-size:.875rem}
.req-table td{padding:7px 8px;border-bottom:1px solid #f0e8e0}
.req-table .pass td:first-child{color:#3a7d44}
.req-table .fail td:first-child{color:#a00}
.req-table .fail{background:#fff5f5}
.summary-table{width:100%;border-collapse:collapse;font-size:.875rem;margin-bottom:16px}
.summary-table th,.summary-table td{padding:8px 10px;border:1px solid #e0d4c8;text-align:left}
.summary-table th{background:#f9f3ee;width:130px;color:#6f4e37}
ul{padding-left:18px;margin-bottom:12px}li{margin-bottom:4px}
code{background:#f0e8e0;padding:1px 5px;border-radius:4px;font-size:.85em}
.text-danger{color:#a00;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>☕ KopiBot AI — Installer</h1>
    <p>Wizard instalasi aplikasi Toko Kopi</p>
  </header>
  {$progress}
  {$content}
</div>
</body>
</html>
HTML;
}
