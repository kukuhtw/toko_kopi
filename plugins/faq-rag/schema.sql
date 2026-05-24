CREATE TABLE IF NOT EXISTS faq_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(20) NOT NULL DEFAULT 'global',
    branch_id INT UNSIGNED NULL,
    parent_global_id INT UNSIGNED NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    tags VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faq_scope_branch_active (scope, branch_id, is_active),
    INDEX idx_faq_parent_global (parent_global_id),
    CONSTRAINT fk_faq_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faq_vectors (
    faq_id INT UNSIGNED NOT NULL PRIMARY KEY,
    vector_dim SMALLINT UNSIGNED NOT NULL,
    vector_json LONGTEXT NOT NULL,
    normalized_text TEXT NOT NULL,
    checksum CHAR(40) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_faq_vector_entry FOREIGN KEY (faq_id) REFERENCES faq_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faq_query_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    conversation_id INT UNSIGNED NULL,
    faq_id INT UNSIGNED NULL,
    query_text TEXT NOT NULL,
    matched_score DECIMAL(8,5) NULL,
    matched_scope VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_faq_query_branch_created (branch_id, created_at),
    INDEX idx_faq_query_faq (faq_id),
    CONSTRAINT fk_faq_query_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_faq_query_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_faq_query_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    CONSTRAINT fk_faq_query_entry FOREIGN KEY (faq_id) REFERENCES faq_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
