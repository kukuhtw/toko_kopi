CREATE TABLE chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    message LONGTEXT NOT NULL,
    meta_json JSON NULL,
    created_at DATETIME NULL,
    KEY idx_tenant_session (tenant_id, session_id),
    KEY idx_role (role),
    KEY idx_created_at (created_at)
);
