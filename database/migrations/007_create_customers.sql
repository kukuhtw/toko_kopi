CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    whatsapp VARCHAR(50) NULL,
    address TEXT NULL,
    source VARCHAR(50) DEFAULT 'web',
    loyalty_point_balance INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_email (email),
    KEY idx_phone (phone),
    KEY idx_whatsapp (whatsapp)
);
