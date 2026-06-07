CREATE TABLE IF NOT EXISTS about_us_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL,
    branch_id INT NULL,
    title VARCHAR(255) NOT NULL,
    short_description TEXT NULL,
    content LONGTEXT NULL,
    ai_prompt LONGTEXT NULL,
    ai_generated_content LONGTEXT NULL,
    generation_model VARCHAR(255) NULL,
    content_status VARCHAR(50) NOT NULL DEFAULT 'draft',
    last_generated_at DATETIME NULL,
    published_at DATETIME NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_about_us_business (business_id),
    KEY idx_about_us_branch (branch_id),
    KEY idx_about_us_status (content_status)
);

CREATE TABLE IF NOT EXISTS about_us_generation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NULL,
    content_id INT UNSIGNED NULL,
    event_name VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    prompt_preview MEDIUMTEXT NULL,
    response_preview MEDIUMTEXT NULL,
    model VARCHAR(255) NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_about_us_logs_branch_created (branch_id, created_at),
    KEY idx_about_us_logs_status (status),
    KEY idx_about_us_logs_content (content_id)
);
