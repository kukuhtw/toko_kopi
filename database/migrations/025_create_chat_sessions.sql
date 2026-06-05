CREATE TABLE chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    channel VARCHAR(50) NOT NULL,
    sender_id VARCHAR(255) NOT NULL,
    session_key VARCHAR(255) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_session_key (tenant_id, branch_id, session_key),
    KEY idx_tenant_branch (tenant_id, branch_id),
    KEY idx_customer_id (customer_id)
);
