CREATE TABLE IF NOT EXISTS crm_notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    event_hash CHAR(40) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    recipient VARCHAR(190) NOT NULL DEFAULT '',
    message_preview TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_event_channel (event_hash, channel),
    INDEX idx_crm_branch_customer (branch_id, customer_id),
    INDEX idx_crm_order (order_id),
    CONSTRAINT crm_notification_logs_ibfk_1 FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT crm_notification_logs_ibfk_2 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT crm_notification_logs_ibfk_3 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
