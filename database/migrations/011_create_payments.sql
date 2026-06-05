CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_gateway VARCHAR(50) NOT NULL,
    reference_no VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL,
    checkout_url TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_reference_no (reference_no),
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_order_id (order_id),
    KEY idx_status (status)
);
