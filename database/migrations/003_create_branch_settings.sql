CREATE TABLE branch_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_branch_id (branch_id),
    KEY idx_setting_key (setting_key)
);
