CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_no VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL,
    channel VARCHAR(50) NOT NULL DEFAULT 'web',
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_order_no (order_no),
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_customer_id (customer_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
);
