CREATE TABLE loyalty_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    point INT NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_customer_id (customer_id),
    KEY idx_transaction_type (transaction_type),
    KEY idx_reference (reference_type, reference_id)
);
