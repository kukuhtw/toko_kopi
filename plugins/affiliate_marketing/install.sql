-- Affiliate Marketing Plugin Database Schema
-- KopiBot / AI Agent Commerce Platform
-- Rule utama:
-- Komisi hanya valid bila order sudah PAID dan tidak ada sengketa selama 7 hari sejak order paid.

CREATE TABLE IF NOT EXISTS affiliate_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    affiliate_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    password_hash VARCHAR(255) NULL,
    status ENUM('active','inactive','banned') DEFAULT 'active',
    commission_type ENUM('percent','fixed') DEFAULT 'percent',
    commission_value DECIMAL(12,2) DEFAULT 0,
    created_by_admin_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    banned_at DATETIME NULL,
    banned_reason TEXT NULL,
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status),
    INDEX idx_affiliate_code (affiliate_code)
);

CREATE TABLE IF NOT EXISTS affiliate_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    campaign_code VARCHAR(100) NOT NULL UNIQUE,
    campaign_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    target_url TEXT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    start_date DATE NULL,
    end_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status),
    INDEX idx_campaign_code (campaign_code)
);

CREATE TABLE IF NOT EXISTS affiliate_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    campaign_id INT NOT NULL,
    tracking_code VARCHAR(100) NOT NULL UNIQUE,
    referral_url TEXT NOT NULL,
    total_click INT DEFAULT 0,
    total_order INT DEFAULT 0,
    total_sales DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate_user_id (affiliate_user_id),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_tracking_code (tracking_code)
);

CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    campaign_id INT NULL,
    tracking_code VARCHAR(100) NOT NULL,
    session_id VARCHAR(150) NULL,
    ip_address VARCHAR(100) NULL,
    user_agent TEXT NULL,
    referrer TEXT NULL,
    landing_url TEXT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    converted_order_id INT NULL,
    INDEX idx_affiliate_user_id (affiliate_user_id),
    INDEX idx_tracking_code (tracking_code),
    INDEX idx_clicked_at (clicked_at),
    INDEX idx_converted_order_id (converted_order_id)
);

CREATE TABLE IF NOT EXISTS affiliate_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    affiliate_user_id INT NOT NULL,
    campaign_id INT NULL,
    tracking_code VARCHAR(100) NULL,
    order_total DECIMAL(15,2) DEFAULT 0,
    order_payment_status ENUM('unpaid','paid','failed','refunded','cancelled') DEFAULT 'unpaid',
    order_paid_at DATETIME NULL,
    clearance_days INT DEFAULT 7,
    clearance_until DATETIME NULL,
    dispute_status ENUM('none','disputed','resolved','refund','cancelled') DEFAULT 'none',
    dispute_at DATETIME NULL,
    commission_type ENUM('percent','fixed') DEFAULT 'percent',
    commission_value DECIMAL(12,2) DEFAULT 0,
    commission_amount DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending','waiting_clearance','approved','rejected','paid','disputed','cancelled') DEFAULT 'pending',
    approved_eligible_at DATETIME NULL,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_affiliate_user_id (affiliate_user_id),
    INDEX idx_status (status),
    INDEX idx_order_payment_status (order_payment_status),
    INDEX idx_clearance_until (clearance_until),
    INDEX idx_dispute_status (dispute_status)
);
