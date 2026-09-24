<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

set_cors();

function product_row(array $row): array
{
    $id = $row['id'];
    $pdo = db();

    // Product images
    $images = $pdo->prepare(
        'SELECT image_url
         FROM product_images
         WHERE product_id = ?
         ORDER BY sort_order, id'
    );
    $images->execute([$id]);

    // Product options
    $options = $pdo->prepare(
        'SELECT option_name, option_value
         FROM product_options
         WHERE product_id = ?
         ORDER BY option_name, sort_order, id'
    );
    $options->execute([$id]);

    $attrs = [];

    foreach ($options->fetchAll() as $o) {
        $attrs[$o['option_name']][] = $o['option_value'];
    }

    // Product variants
    $variants = $pdo->prepare(
        'SELECT id, sku, size_value, colour_value, attributes_json,
                price, sale_price, stock, image_url
         FROM product_variants
         WHERE product_id = ?
         ORDER BY id'
    );
    $variants->execute([$id]);

    // Variation images
    $variation = $pdo->prepare(
        'SELECT colour_value, image_url
         FROM product_variation_images
         WHERE product_id = ?'
    );
    $variation->execute([$id]);

    $variationImages = [];

    foreach ($variation->fetchAll() as $v) {
        $variationImages[$v['colour_value']] = $v['image_url'];
    }

    $out = $row;

    // Images
    $out['images'] = array_column(
        $images->fetchAll(),
        'image_url'
    );

    // Attributes
    $out['attributes'] = array_map(
        fn($name, $values) => [
            'name' => $name,
            'selectedOptions' => $values
        ],
        array_keys($attrs),
        $attrs
    );

    // Variation images
    $out['variationImages'] = $variationImages;

    // Variants
    $out['variants'] = array_map(
        function ($v) {

            $attrs = json_decode(
                (string)$v['attributes_json'],
                true
            ) ?: [];

            if ($v['size_value'] !== null) {
                $attrs['Size'] = $v['size_value'];
            }

            if ($v['colour_value'] !== null) {
                $attrs['Colour'] = $v['colour_value'];
            }

            return [
                'id' => $v['id'],
                'sku' => $v['sku'],
                'attributes' => $attrs,
                'price' => $v['price'] !== null
                    ? (float)$v['price']
                    : null,
                'salePrice' => $v['sale_price'] !== null
                    ? (float)$v['sale_price']
                    : null,
                'stock' => (int)$v['stock'],
                'imageUrl' => $v['image_url']
            ];
        },
        $variants->fetchAll()
    );

    // Price
    $out['price'] = (float)$out['price'];

    $out['salePrice'] = $out['sale_price'] !== null
        ? (float)$out['sale_price']
        : null;

    unset(
        $out['sale_price'],
        $out['cost_price']
    );

    return $out;
}


function write_product(
    array $payload,
    ?string $existingId = null
): array {

    $pdo = db();

    $now = date('Y-m-d H:i:s');

    // Product ID
    $id = $existingId ?: id_or_new(
        $payload['id'] ?? null
    );

    // Basic information
    $name = clean_string(
        $payload['name'] ?? '',
        255
    );

    $sku = clean_string(
        $payload['sku'] ?? '',
        100
    );

    if ($name === '' || $sku === '') {
        json_response(
            [
                'success' => false,
                'error' => 'name and sku are required.'
            ],
            422
        );
    }

    // Slug
    $slug = clean_string(
        $payload['slug']
            ?? strtolower(
                trim(
                    preg_replace(
                        '/[^a-z0-9]+/i',
                        '-',
                        $name
                    ),
                    '-'
                )
            ),
        255
    );

    // Attributes
    $attributes = is_array(
        $payload['attributes'] ?? null
    )
        ? $payload['attributes']
        : [];

    $options = [];

    foreach ($attributes as $attr) {

        $optionName = clean_string(
            $attr['name']
                ?? $attr['attributeId']
                ?? '',
            80
        );

        $values = is_array(
            $attr['selectedOptions'] ?? null
        )
            ? $attr['selectedOptions']
            : [];

        if ($optionName !== '') {

            foreach ($values as $value) {

                $cleanValue = clean_string(
                    $value,
                    120
                );

                if ($cleanValue !== '') {
                    $options[] = [
                        $optionName,
                        $cleanValue
                    ];
                }
            }
        }
    }

    // Inventory
    $inventory = is_array(
        $payload['inventory'] ?? null
    )
        ? $payload['inventory']
        : [];

    /*
     * IMPORTANT:
     *
     * The database fields below are integer fields.
     * We explicitly convert them to 0 or 1.
     *
     * This fixes:
     *
     * Incorrect integer value: ''
     *
     * for allow_backorder.
     */

    $trackStock = !empty(
        $inventory['trackStock']
    ) ? 1 : 0;

    $allowBackorder = !empty(
        $inventory['allowBackorder']
    ) ? 1 : 0;

    $isFeatured = !empty(
        $payload['isFeatured']
    ) ? 1 : 0;

    $isNewArrival = !empty(
        $payload['isNewArrival']
    ) ? 1 : 0;

    $isBestSeller = !empty(
        $payload['isBestSeller']
    ) ? 1 : 0;

    // Main product data
    $data = [

        $id,

        store_id(),

        $name,

        $slug,

        $sku,

        clean_string(
            $payload['brand'] ?? '',
            255
        ) ?: null,

        (string)(
            $payload['description'] ?? ''
        ),

        clean_string(
            $payload['shortDescription'] ?? '',
            500
        ) ?: null,

        // Price
        (float)(
            $payload['price'] ?? 0
        ),

        // Sale price
        ($payload['salePrice'] ?? null) !== null
            ? (float)$payload['salePrice']
            : null,

        // Cost price
        ($payload['costPrice'] ?? null) !== null
            ? (float)$payload['costPrice']
            : null,

        // Status
        in_array(
            ($payload['status'] ?? 'active'),
            [
                'active',
                'draft',
                'archived'
            ],
            true
        )
            ? $payload['status']
            : 'active',

        // Inventory quantity
        (int)(
            $inventory['quantity'] ?? 0
        ),

        // Track stock
        $trackStock,

        // Allow backorder
        $allowBackorder,

        // Low stock threshold
        (int)(
            $inventory['lowStockThreshold'] ?? 3
        ),

        // Featured
        $isFeatured,

        // New arrival
        $isNewArrival,

        // Best seller
        $isBestSeller,

        // Rating
        (float)(
            $payload['rating'] ?? 0
        ),

        // Review count
        (int)(
            $payload['reviewCount'] ?? 0
        ),

        // SEO
        json_encode(
            $payload['seo'] ?? [],
            JSON_UNESCAPED_UNICODE
        ),

        // Tags
        json_encode(
            $payload['tags'] ?? [],
            JSON_UNESCAPED_UNICODE
        ),

        // Dates
        $now,

        $now
    ];


    /*
     * SAVE PRODUCT
     */

    $pdo->beginTransaction();

    try {

        $sql = '
            INSERT INTO products (
                id,
                store_id,
                name,
                slug,
                sku,
                brand,
                description,
                short_description,
                price,
                sale_price,
                cost_price,
                status,
                inventory_quantity,
                track_stock,
                allow_backorder,
                low_stock_threshold,
                is_featured,
                is_new_arrival,
                is_best_seller,
                rating,
                review_count,
                seo_json,
                tags_json,
                created_at,
                updated_at
            )
            VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )

            ON DUPLICATE KEY UPDATE

                name = VALUES(name),
                slug = VALUES(slug),
                sku = VALUES(sku),
                brand = VALUES(brand),
                description = VALUES(description),
                short_description = VALUES(short_description),
                price = VALUES(price),
                sale_price = VALUES(sale_price),
                cost_price = VALUES(cost_price),
                status = VALUES(status),
                inventory_quantity = VALUES(inventory_quantity),
                track_stock = VALUES(track_stock),
                allow_backorder = VALUES(allow_backorder),
                low_stock_threshold = VALUES(low_stock_threshold),
                is_featured = VALUES(is_featured),
                is_new_arrival = VALUES(is_new_arrival),
                is_best_seller = VALUES(is_best_seller),
                rating = VALUES(rating),
                review_count = VALUES(review_count),
                seo_json = VALUES(seo_json),
                tags_json = VALUES(tags_json),
                updated_at = VALUES(updated_at)
        ';

        $stmt = $pdo->prepare($sql);

        $stmt->execute($data);


        /*
         * PRODUCT IMAGES
         */

        $pdo->prepare(
            'DELETE FROM product_images
             WHERE product_id = ?'
        )->execute([$id]);

        $images = is_array(
            $payload['images'] ?? null
        )
            ? $payload['images']
            : [];

        $stmt = $pdo->prepare(
            'INSERT INTO product_images
             (
                product_id,
                image_url,
                sort_order,
                is_primary
             )
             VALUES (?,?,?,?)'
        );

        foreach ($images as $i => $url) {

            if (
                is_string($url)
                && trim($url) !== ''
            ) {

                $stmt->execute([
                    $id,
                    clean_string($url, 1000),
                    $i,
                    $i === 0 ? 1 : 0
                ]);
            }
        }


        /*
         * PRODUCT OPTIONS
         */

        $pdo->prepare(
            'DELETE FROM product_options
             WHERE product_id = ?'
        )->execute([$id]);

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO product_options
             (
                product_id,
                option_name,
                option_value,
                sort_order
             )
             VALUES (?,?,?,?)'
        );

        foreach ($options as $i => $o) {

            $stmt->execute([
                $id,
                $o[0],
                $o[1],
                $i
            ]);
        }


        /*
         * PRODUCT VARIANTS
         */

        $pdo->prepare(
            'DELETE FROM product_variants
             WHERE product_id = ?'
        )->execute([$id]);

        $stmt = $pdo->prepare(
            'INSERT INTO product_variants
             (
                id,
                product_id,
                sku,
                size_value,
                colour_value,
                attributes_json,
                price,
                sale_price,
                stock,
                image_url
             )
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );

        foreach (
            ($payload['variants'] ?? [])
            as $v
        ) {

            $va = is_array(
                $v['attributes'] ?? null
            )
                ? $v['attributes']
                : [];

            $variantId = id_or_new(
                $v['id'] ?? null
            );

            $variantSku = clean_string(
                $v['sku'] ?? '',
                120
            );

            $sizeValue =
                $va['Size']
                ?? null;

            $colourValue =
                $va['Colour']
                ?? ($va['Color'] ?? null);

            $variantPrice =
                (float)(
                    $v['price']
                    ?? $payload['price']
                    ?? 0
                );

            $variantSalePrice =
                ($v['salePrice'] ?? null) !== null
                    ? (float)$v['salePrice']
                    : null;

            $variantStock =
                (int)(
                    $v['stock'] ?? 0
                );

            $variantImage =
                isset($v['imageUrl'])
                    ? clean_string(
                        (string)$v['imageUrl'],
                        1000
                    )
                    : null;

            $stmt->execute([
                $variantId,
                $id,
                $variantSku,
                $sizeValue,
                $colourValue,
                json_encode(
                    $va,
                    JSON_UNESCAPED_UNICODE
                ),
                $variantPrice,
                $variantSalePrice,
                $variantStock,
                $variantImage
            ]);
        }


        /*
         * VARIATION IMAGES
         */

        $pdo->prepare(
            'DELETE FROM product_variation_images
             WHERE product_id = ?'
        )->execute([$id]);

        $stmt = $pdo->prepare(
            'INSERT INTO product_variation_images
             (
                product_id,
                colour_value,
                image_url
             )
             VALUES (?,?,?)'
        );

        foreach (
            ($payload['variationImages'] ?? [])
            as $colour => $url
        ) {

            if (
                is_string($url)
                && trim($url) !== ''
            ) {

                $stmt->execute([
                    $id,
                    clean_string(
                        (string)$colour,
                        120
                    ),
                    clean_string(
                        $url,
                        1000
                    )
                ]);
            }
        }


        // Everything succeeded
        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }


    /*
     * RETURN THE SAVED PRODUCT
     */

    $q = $pdo->prepare(
        'SELECT *
         FROM products
         WHERE id = ?'
    );

    $q->execute([$id]);

    $row = $q->fetch();

    if (!$row) {
        throw new RuntimeException(
            'Product was saved but could not be retrieved.'
        );
    }

    return product_row($row);
}


/*
 * API ROUTER
 */

try {

    $pdo = db();

    /*
     * GET PRODUCTS
     */

    if (
        $_SERVER['REQUEST_METHOD']
        === 'GET'
    ) {

        $id = $_GET['id'] ?? null;

        // Get one product
        if ($id) {

            $q = $pdo->prepare(
                'SELECT *
                 FROM products
                 WHERE id = ?
                 AND store_id = ?'
            );

            $q->execute([
                $id,
                store_id()
            ]);

            $row = $q->fetch();

            if (!$row) {

                json_response(
                    [
                        'success' => false,
                        'error' => 'Product not found.'
                    ],
                    404
                );
            }

            json_response([
                'success' => true,
                'product' => product_row($row)
            ]);
        }


        // Get all products
        $q = $pdo->prepare(
            'SELECT *
             FROM products
             WHERE store_id = ?
             ORDER BY created_at DESC'
        );

        $q->execute([
            store_id()
        ]);

        $rows = $q->fetchAll();

        json_response([
            'success' => true,
            'products' => array_map(
                'product_row',
                $rows
            )
        ]);
    }


    /*
     * ADMIN TOKEN
     *
     * Required for POST, PUT and DELETE.
     */

    require_admin_token();


    /*
     * CREATE PRODUCT
     */

    if (
        $_SERVER['REQUEST_METHOD']
        === 'POST'
    ) {

        $product = write_product(
            request_json()
        );

        json_response(
            [
                'success' => true,
                'product' => $product
            ],
            201
        );
    }


    /*
     * UPDATE PRODUCT
     */

    if (
        $_SERVER['REQUEST_METHOD']
        === 'PUT'
    ) {

        $id = $_GET['id'] ?? null;

        if (!$id) {

            json_response(
                [
                    'success' => false,
                    'error' => 'Missing id.'
                ],
                422
            );
        }

        $product = write_product(
            request_json(),
            $id
        );

        json_response([
            'success' => true,
            'product' => $product
        ]);
    }


    /*
     * DELETE PRODUCT
     */

    if (
        $_SERVER['REQUEST_METHOD']
        === 'DELETE'
    ) {

        $id = $_GET['id'] ?? null;

        if (!$id) {

            json_response(
                [
                    'success' => false,
                    'error' => 'Missing id.'
                ],
                422
            );
        }

        $q = $pdo->prepare(
            'DELETE FROM products
             WHERE id = ?
             AND store_id = ?'
        );

        $q->execute([
            $id,
            store_id()
        ]);

        json_response([
            'success' => $q->rowCount() > 0
        ]);
    }


    /*
     * Unsupported method
     */

    json_response(
        [
            'success' => false,
            'error' => 'Method not allowed.'
        ],
        405
    );


} catch (Throwable $e) {

    /*
     * Write the REAL error to the PHP error log.
     * The browser only receives a generic error.
     */

    error_log((string)$e);

    json_response(
        [
            'success' => false,
            'error' => 'Server error. Check PHP error log.'
        ],
        500
    );
}
?>