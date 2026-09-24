CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(64) PRIMARY KEY,
  store_id VARCHAR(64) NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  brand VARCHAR(255) NULL,
  description TEXT NULL,
  short_description VARCHAR(500) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NULL,
  status ENUM('active','draft','archived') NOT NULL DEFAULT 'active',
  inventory_quantity INT NOT NULL DEFAULT 0,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  allow_backorder TINYINT(1) NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 3,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
  rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  review_count INT NOT NULL DEFAULT 0,
  seo_json JSON NULL,
  tags_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_store_sku (store_id, sku),
  KEY idx_products_store_status (store_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(64) NOT NULL,
  image_url VARCHAR(1000) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  KEY idx_images_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(64) NOT NULL,
  option_name VARCHAR(80) NOT NULL,
  option_value VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_options_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_option (product_id, option_name, option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id VARCHAR(64) PRIMARY KEY,
  product_id VARCHAR(64) NOT NULL,
  sku VARCHAR(120) NOT NULL,
  size_value VARCHAR(120) NULL,
  colour_value VARCHAR(120) NULL,
  attributes_json JSON NULL,
  price DECIMAL(12,2) NULL,
  sale_price DECIMAL(12,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  image_url VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_variant_sku (product_id, sku),
  KEY idx_variants_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variation_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(64) NOT NULL,
  colour_value VARCHAR(120) NOT NULL,
  image_url VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_variation_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_colour_image (product_id, colour_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
