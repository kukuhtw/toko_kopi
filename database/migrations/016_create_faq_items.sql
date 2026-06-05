CREATE TABLE faq_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    category VARCHAR(100) NULL,
    question TEXT NOT NULL,
    answer LONGTEXT NOT NULL,
    tags VARCHAR(255) NULL,
    is_active TINYINT DEFAULT 1,
    embedding_status TINYINT DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_category (category),
    KEY idx_active (is_active)
);
