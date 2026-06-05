CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_code VARCHAR(100) NOT NULL,
    tenant_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    owner_email VARCHAR(255) NOT NULL,
    owner_phone VARCHAR(50) NOT NULL,
    subscription_plan VARCHAR(50) DEFAULT 'FREE',
    status VARCHAR(50) DEFAULT 'active',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_tenant_code (tenant_code)
);
