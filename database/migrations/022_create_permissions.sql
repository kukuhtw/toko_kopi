CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_code VARCHAR(150) NOT NULL,
    permission_name VARCHAR(150) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_permission_code (permission_code)
);
