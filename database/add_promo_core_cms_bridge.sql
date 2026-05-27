ALTER TABLE promo_contents
    ADD COLUMN core_promo_source VARCHAR(50) NULL AFTER branch_id,
    ADD COLUMN core_promo_id INT NULL AFTER core_promo_source,
    ADD COLUMN ai_banner_prompt LONGTEXT NULL AFTER banner_image,
    ADD COLUMN ai_banner_image_url TEXT NULL AFTER ai_banner_prompt,
    ADD COLUMN is_homepage_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER publish_status;

CREATE TABLE IF NOT EXISTS promo_cms_generation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_content_id INT NULL,
    core_promo_source VARCHAR(50) NULL,
    core_promo_id INT NULL,
    generation_type VARCHAR(50) NOT NULL,
    prompt LONGTEXT NULL,
    output LONGTEXT NULL,
    model VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'success',
    message TEXT NULL,
    created_at DATETIME NULL,
    KEY idx_promo_cms_generation_content (promo_content_id),
    KEY idx_promo_cms_generation_core (core_promo_source, core_promo_id),
    KEY idx_promo_cms_generation_type (generation_type)
);
