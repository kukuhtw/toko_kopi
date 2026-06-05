CREATE TABLE branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_code VARCHAR(100) NOT NULL,
    branch_name VARCHAR(255) NOT NULL,
    city VARCHAR(100) NULL,
    timezone VARCHAR(100) DEFAULT 'Asia/Jakarta',
    is_active TINYINT DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_branch_code (branch_code),
    KEY idx_tenant_id (tenant_id)
);
