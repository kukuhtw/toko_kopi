CREATE TABLE promo_redemptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    promo_id BIGINT UNSIGNED NOT NULL,
    promo_code VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_promo_id (promo_id),
    KEY idx_customer_id (customer_id),
    KEY idx_order_id (order_id)
);
