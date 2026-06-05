CREATE TABLE faq_unanswered (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    question TEXT NOT NULL,
    is_resolved TINYINT DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_customer_id (customer_id),
    KEY idx_resolved (is_resolved)
);
