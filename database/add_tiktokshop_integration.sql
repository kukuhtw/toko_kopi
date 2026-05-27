CREATE TABLE IF NOT EXISTS tiktokshop_seller_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id BIGINT NOT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    access_token_expire_in BIGINT NULL,
    refresh_token_expire_in BIGINT NULL,
    seller_name VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_tiktokshop_seller_id (seller_id)
);

CREATE TABLE IF NOT EXISTS tiktokshop_product_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    local_sku VARCHAR(100) NULL,
    local_barcode VARCHAR(100) NULL,
    tiktok_product_id BIGINT NULL,
    tiktok_sku_id BIGINT NULL,
    sync_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    last_sync_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_tiktokshop_mapping_item (menu_item_id),
    KEY idx_tiktokshop_mapping_sync (sync_status)
);

CREATE TABLE IF NOT EXISTS tiktokshop_orders_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) NOT NULL,
    order_status VARCHAR(100) NULL,
    customer_name VARCHAR(255) NULL,
    total_amount DECIMAL(14,2) NULL,
    raw_payload LONGTEXT NULL,
    synced_at DATETIME NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_tiktokshop_order_id (order_id)
);

CREATE TABLE IF NOT EXISTS tiktokshop_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(255) NULL,
    raw_payload LONGTEXT NULL,
    created_at DATETIME NULL,
    KEY idx_tiktokshop_webhook_event (event_name)
);
