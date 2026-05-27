-- Pharmacy / Apotek Module Migration
-- Run after database/schema.sql

CREATE TABLE IF NOT EXISTS pharmacy_product_metadata (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_item_id INT UNSIGNED NOT NULL,
  generic_name VARCHAR(190) DEFAULT NULL,
  bpom_no VARCHAR(100) DEFAULT NULL,
  manufacturer VARCHAR(190) DEFAULT NULL,
  dosage VARCHAR(100) DEFAULT NULL,
  dosage_form VARCHAR(100) DEFAULT NULL,
  drug_class VARCHAR(100) DEFAULT NULL,
  requires_prescription TINYINT(1) NOT NULL DEFAULT 0,
  pharmacist_review_required TINYINT(1) NOT NULL DEFAULT 0,
  warning_text TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pharmacy_meta_menu_item (menu_item_id),
  KEY idx_pharmacy_meta_bpom (bpom_no),
  KEY idx_pharmacy_meta_prescription (requires_prescription)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_inventory_stock (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED NOT NULL,
  menu_item_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED DEFAULT NULL,
  sku VARCHAR(120) NOT NULL,
  batch_no VARCHAR(120) DEFAULT NULL,
  expired_date DATE DEFAULT NULL,
  stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  minimum_stock_qty DECIMAL(12,2) NOT NULL DEFAULT 5.00,
  unit VARCHAR(40) NOT NULL DEFAULT 'pcs',
  rack_location VARCHAR(120) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_pharmacy_stock_sku (sku),
  KEY idx_pharmacy_stock_branch (branch_id),
  KEY idx_pharmacy_stock_item (menu_item_id),
  KEY idx_pharmacy_stock_variant (variant_id),
  KEY idx_pharmacy_stock_expired (expired_date),
  KEY idx_pharmacy_stock_low (branch_id, stock_qty, minimum_stock_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_stock_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stock_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  menu_item_id INT UNSIGNED NOT NULL,
  movement_type ENUM('in','out','adjustment','sale','return','expired','transfer') NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  before_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  after_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reference_type VARCHAR(50) DEFAULT NULL,
  reference_id BIGINT UNSIGNED DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pharmacy_movement_stock (stock_id),
  KEY idx_pharmacy_movement_branch_item (branch_id, menu_item_id),
  KEY idx_pharmacy_movement_type (movement_type),
  KEY idx_pharmacy_movement_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_bpom_import_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) DEFAULT NULL,
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  success_rows INT UNSIGNED NOT NULL DEFAULT 0,
  failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
  error_log MEDIUMTEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_consultation_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED DEFAULT NULL,
  customer_id INT UNSIGNED DEFAULT NULL,
  channel VARCHAR(50) DEFAULT NULL,
  question TEXT NOT NULL,
  ai_response MEDIUMTEXT DEFAULT NULL,
  risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  pharmacist_review_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pharmacy_consult_branch (branch_id),
  KEY idx_pharmacy_consult_risk (risk_level),
  KEY idx_pharmacy_consult_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_pos_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED NOT NULL,
  cashier_user_id INT UNSIGNED DEFAULT NULL,
  opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  closing_cash DECIMAL(12,2) DEFAULT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pharmacy_pos_session_branch (branch_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_pos_sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  branch_id INT UNSIGNED NOT NULL,
  invoice_no VARCHAR(100) NOT NULL,
  customer_name VARCHAR(190) DEFAULT NULL,
  customer_phone VARCHAR(50) DEFAULT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
  payment_status ENUM('unpaid','paid','void') NOT NULL DEFAULT 'paid',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_pharmacy_pos_invoice (invoice_no),
  KEY idx_pharmacy_pos_sales_branch (branch_id),
  KEY idx_pharmacy_pos_sales_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pharmacy_pos_sale_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id BIGINT UNSIGNED NOT NULL,
  stock_id INT UNSIGNED DEFAULT NULL,
  menu_item_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED DEFAULT NULL,
  item_name VARCHAR(190) NOT NULL,
  sku VARCHAR(120) DEFAULT NULL,
  batch_no VARCHAR(120) DEFAULT NULL,
  expired_date DATE DEFAULT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  requires_prescription TINYINT(1) NOT NULL DEFAULT 0,
  prescription_verified TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pharmacy_pos_item_sale (sale_id),
  KEY idx_pharmacy_pos_item_stock (stock_id),
  KEY idx_pharmacy_pos_item_prescription (requires_prescription)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
