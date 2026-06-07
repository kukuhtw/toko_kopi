<?php

declare(strict_types=1);

use KopiBot\Core\DatabaseConnection;

final class AboutUsAiRepository
{
    public const PLUGIN_SLUG = 'about-us-ai';

    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS about_us_contents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                business_id INT NULL,
                branch_id INT NULL,
                title VARCHAR(255) NOT NULL,
                short_description TEXT NULL,
                content LONGTEXT NULL,
                ai_prompt LONGTEXT NULL,
                ai_generated_content LONGTEXT NULL,
                generation_model VARCHAR(255) NULL,
                content_status VARCHAR(50) NOT NULL DEFAULT "draft",
                last_generated_at DATETIME NULL,
                published_at DATETIME NULL,
                created_by VARCHAR(255) NULL,
                updated_by VARCHAR(255) NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                KEY idx_about_us_business (business_id),
                KEY idx_about_us_branch (branch_id),
                KEY idx_about_us_status (content_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS about_us_generation_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                branch_id INT UNSIGNED NULL,
                content_id INT UNSIGNED NULL,
                event_name VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                prompt_preview MEDIUMTEXT NULL,
                response_preview MEDIUMTEXT NULL,
                model VARCHAR(255) NULL,
                last_error TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_about_us_logs_branch_created (branch_id, created_at),
                INDEX idx_about_us_logs_status (status),
                INDEX idx_about_us_logs_content (content_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function getBranchSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function setBranchSetting(int $branchId, string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key, $value]);
    }

    public function getGlobalSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$this->globalKey($key)]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function setGlobalSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO app_settings (setting_key, setting_val)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        );
        $stmt->execute([$this->globalKey($key), $value]);
    }

    public function saveContent(
        int $branchId,
        string $title,
        string $shortDescription,
        string $content,
        string $status,
        ?string $prompt = null,
        ?string $generatedContent = null,
        ?string $model = null,
        ?string $updatedBy = null
    ): int {
        $existing = $this->findLatestByBranch($branchId);

        if ($existing !== null) {
            $stmt = $this->db->prepare(
                'UPDATE about_us_contents
                 SET title = ?, short_description = ?, content = ?, ai_prompt = ?, ai_generated_content = ?,
                     generation_model = ?, content_status = ?, updated_by = ?, updated_at = NOW(),
                     last_generated_at = ?, published_at = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $title,
                $shortDescription,
                $content,
                $prompt,
                $generatedContent,
                $model,
                $status,
                $updatedBy,
                $generatedContent !== null ? date('Y-m-d H:i:s') : null,
                $status === 'published' ? date('Y-m-d H:i:s') : null,
                $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO about_us_contents
             (business_id, branch_id, title, short_description, content, ai_prompt, ai_generated_content,
              generation_model, content_status, last_generated_at, published_at, created_by, updated_by, created_at, updated_at)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $branchId,
            $title,
            $shortDescription,
            $content,
            $prompt,
            $generatedContent,
            $model,
            $status,
            $generatedContent !== null ? date('Y-m-d H:i:s') : null,
            $status === 'published' ? date('Y-m-d H:i:s') : null,
            $updatedBy,
            $updatedBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findLatestByBranch(int $branchId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM about_us_contents
             WHERE branch_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$branchId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function addGenerationLog(
        int $branchId,
        ?int $contentId,
        string $eventName,
        string $status,
        ?string $prompt = null,
        ?string $response = null,
        ?string $model = null,
        ?string $lastError = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO about_us_generation_logs
             (branch_id, content_id, event_name, status, prompt_preview, response_preview, model, last_error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$branchId, $contentId, $eventName, $status, $prompt, $response, $model, $lastError]);
    }

    public function getRecentLogs(?int $branchId = null, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        if ($branchId !== null && $branchId > 0) {
            $stmt = $this->db->prepare(
                'SELECT * FROM about_us_generation_logs
                 WHERE branch_id = ?
                 ORDER BY id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $branchId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM about_us_generation_logs
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function globalKey(string $key): string
    {
        return self::PLUGIN_SLUG . '.' . $key;
    }
}
