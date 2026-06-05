CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    role_code VARCHAR(100) NOT NULL,
    role_name VARCHAR(150) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_tenant_role_code (tenant_id, role_code)
);
