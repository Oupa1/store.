-- Poder Emporium: empty the product catalogue while keeping the store and admin tables.
-- Run this in phpMyAdmin after selecting the correct Poder Emporium database.
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE product_variation_images;
TRUNCATE TABLE product_variants;
TRUNCATE TABLE product_options;
TRUNCATE TABLE product_images;
TRUNCATE TABLE products;
SET FOREIGN_KEY_CHECKS = 1;
