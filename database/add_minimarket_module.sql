CREATE TABLE IF NOT EXISTS minimarket_product_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    sku VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,
    brand VARCHAR(150) NULL,
    category VARCHAR(150) NULL,
    unit VARCHAR(50) NULL,
    pack_size VARCHAR(100) NULL,
    has_expiry TINYINT(1) NOT NULL DEFAULT 1,
    low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 5,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_minimarket_menu_item (menu_item_id),
    UNIQUE KEY uq_minimarket_sku (sku),
    KEY idx_minimarket_barcode (barcode),
    KEY idx_minimarket_category (category)
);

CREATE TABLE IF NOT EXISTS minimarket_inventory_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL DEFAULT 1,
    menu_item_id INT NOT NULL,
    sku VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,
    batch_no VARCHAR(100) NULL,
    expired_date DATE NULL,
    qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    purchase_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    supplier_name VARCHAR(150) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_minimarket_stock_item (branch_id, menu_item_id),
    KEY idx_minimarket_stock_expired (expired_date),
    KEY idx_minimarket_stock_barcode (barcode)
);

CREATE TABLE IF NOT EXISTS minimarket_stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL DEFAULT 1,
    menu_item_id INT NOT NULL,
    stock_id INT NULL,
    movement_type VARCHAR(50) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    reference_type VARCHAR(100) NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    KEY idx_minimarket_movement_item (branch_id, menu_item_id),
    KEY idx_minimarket_movement_ref (reference_type, reference_id),
    KEY idx_minimarket_movement_type (movement_type)
);

CREATE TABLE IF NOT EXISTS minimarket_pos_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL DEFAULT 1,
    cashier_name VARCHAR(150) NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    opening_cash DECIMAL(14,2) NOT NULL DEFAULT 0,
    closing_cash DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'open'
);

CREATE TABLE IF NOT EXISTS minimarket_pos_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL DEFAULT 1,
    session_id INT NULL,
    invoice_no VARCHAR(100) NOT NULL,
    customer_name VARCHAR(150) NULL,
    customer_phone VARCHAR(50) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'paid',
    created_at DATETIME NULL,
    UNIQUE KEY uq_minimarket_invoice_no (invoice_no),
    KEY idx_minimarket_sales_date (created_at)
);

CREATE TABLE IF NOT EXISTS minimarket_pos_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,
    qty DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    KEY idx_minimarket_sale_items_sale (sale_id),
    KEY idx_minimarket_sale_items_item (menu_item_id)
);
