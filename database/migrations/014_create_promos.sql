CREATE TABLE promos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    promo_code VARCHAR(50) NOT NULL,
    promo_name VARCHAR(255) NOT NULL,
    promo_type VARCHAR(50) NOT NULL,
    promo_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    minimum_order DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_discount DECIMAL(15,2) NOT NULL DEFAULT 0,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_tenant_promo_code (tenant_id, promo_code),
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_is_active (is_active)
);
