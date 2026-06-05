CREATE TABLE chat_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    session_key VARCHAR(255) NOT NULL,
    state VARCHAR(100) NOT NULL DEFAULT 'idle',
    payload_json JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_chat_state (tenant_id, branch_id, session_key),
    KEY idx_customer_id (customer_id),
    KEY idx_state (state)
);
