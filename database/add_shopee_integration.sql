CREATE TABLE IF NOT EXISTS shopee_shop_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id BIGINT NOT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    expire_in BIGINT NULL,
    refresh_token_expire_in BIGINT NULL,
    merchant_name VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_shopee_shop_id (shop_id)
);

CREATE TABLE IF NOT EXISTS shopee_product_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    local_sku VARCHAR(100) NULL,
    local_barcode VARCHAR(100) NULL,
    shopee_item_id BIGINT NULL,
    shopee_model_id BIGINT NULL,
    sync_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    last_sync_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_shopee_mapping_item (menu_item_id),
    KEY idx_shopee_mapping_sync (sync_status)
);

CREATE TABLE IF NOT EXISTS shopee_orders_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_sn VARCHAR(100) NOT NULL,
    order_status VARCHAR(100) NULL,
    customer_name VARCHAR(255) NULL,
    total_amount DECIMAL(14,2) NULL,
    raw_payload LONGTEXT NULL,
    synced_at DATETIME NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_shopee_order_sn (order_sn)
);

CREATE TABLE IF NOT EXISTS shopee_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(255) NULL,
    raw_payload LONGTEXT NULL,
    created_at DATETIME NULL,
    KEY idx_shopee_webhook_event (event_name)
);
