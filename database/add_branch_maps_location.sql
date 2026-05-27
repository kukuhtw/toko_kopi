CREATE TABLE IF NOT EXISTS branch_maps_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    branch_code VARCHAR(100) NULL,
    branch_name VARCHAR(255) NOT NULL,
    address TEXT NULL,
    latitude DECIMAL(12,8) NULL,
    longitude DECIMAL(12,8) NULL,
    google_place_id VARCHAR(255) NULL,
    google_maps_url TEXT NULL,
    google_embed_url TEXT NULL,
    contact_phone VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_branch_maps_branch_id (branch_id),
    KEY idx_branch_maps_branch_code (branch_code),
    KEY idx_branch_maps_active (is_active)
);
