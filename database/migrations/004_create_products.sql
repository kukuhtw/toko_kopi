CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NULL,
    description LONGTEXT NULL,
    base_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    image_url TEXT NULL,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_category (category),
    KEY idx_active (is_active)
);
