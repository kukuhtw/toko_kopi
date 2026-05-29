CREATE TABLE IF NOT EXISTS affiliate_fraud_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    order_id INT NULL,
    risk_score INT DEFAULT 0,
    risk_level ENUM('clean','low','medium','high') DEFAULT 'clean',
    reasons JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_affiliate_user_id (affiliate_user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at)
);
