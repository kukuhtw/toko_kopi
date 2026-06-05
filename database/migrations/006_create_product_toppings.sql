CREATE TABLE product_toppings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    topping_name VARCHAR(100) NOT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_product_id (product_id),
    KEY idx_tenant_branch (tenant_id, branch_id)
);
