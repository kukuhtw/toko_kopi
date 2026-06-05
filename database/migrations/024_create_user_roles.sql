CREATE TABLE user_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_user_role (tenant_id, user_id, role_id),
    KEY idx_user_id (user_id),
    KEY idx_role_id (role_id)
);
