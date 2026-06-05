CREATE TABLE crm_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload_json JSON NULL,
    source VARCHAR(50) DEFAULT 'system',
    created_at DATETIME NULL,
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_customer_id (customer_id),
    KEY idx_event_type (event_type),
    KEY idx_created_at (created_at)
);
