<?php
// Copy to config.php and fill in your cPanel database values.
// Keep config.php outside public_html when possible, or protect it with .htaccess.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'poderemp_poder',
        'user' => 'poderemp_poder',
        'pass' => 'cxJddwfhb6u86jTUSv4n',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'store_id' => 'store-poderemporium-001',
        'admin_token' => 'PoderPE_2026_7xK92mQ4vL8nT5zR',
        'upload_dir' => __DIR__ . '/uploads/products',
        'upload_url' => '/poder-api/uploads/products',
        'max_upload_bytes' => 8 * 1024 * 1024,
        'allowed_origins' => ['*'],
    ],
];
?>

// IMPORTANT: rename this file to config.php and never share config.php.
