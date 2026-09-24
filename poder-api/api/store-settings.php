<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

set_cors();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();
$storeId = store_id();

/*
|--------------------------------------------------------------------------
| GET STORE SETTINGS
|--------------------------------------------------------------------------
| Public.
|
| Used by the storefront to load the latest branding/settings from MySQL.
|
*/

if ($method === 'GET') {

    try {

        $stmt = $pdo->prepare(
            'SELECT store_id, store_data, updated_at
             FROM store_settings
             WHERE store_id = ?
             LIMIT 1'
        );

        $stmt->execute([$storeId]);

        $row = $stmt->fetch();

        if (!$row) {

            json_response([
                'success' => true,
                'exists' => false,
                'storeId' => $storeId,
                'store' => null
            ]);
        }

        $store = json_decode($row['store_data'], true);

        if (!is_array($store)) {

            json_response([
                'success' => false,
                'error' => 'Stored store configuration is invalid JSON.'
            ], 500);
        }

        json_response([
            'success' => true,
            'exists' => true,
            'storeId' => $row['store_id'],
            'updatedAt' => $row['updated_at'],
            'store' => $store
        ]);

    } catch (Throwable $e) {

        error_log(
            'Store settings GET error: ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'error' => 'Unable to load store settings.'
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| POST STORE SETTINGS
|--------------------------------------------------------------------------
| Admin.
|
| Creates or replaces the store configuration.
|
*/

if ($method === 'POST') {

    require_admin_token();

    try {

        $payload = request_json();

        /*
        | Accept either:
        |
        | {
        |   "store": {...}
        | }
        |
        | or directly:
        |
        | {
        |   "id": "...",
        |   "branding": {...}
        | }
        */

        $store = $payload['store'] ?? $payload;

        if (!is_array($store)) {

            json_response([
                'success' => false,
                'error' => 'Invalid store configuration.'
            ], 400);
        }

        /*
        | Always force the correct store ID.
        */

        $store['id'] = $storeId;

        /*
        | Keep the timestamp inside the store configuration too.
        */

        $store['updatedAt'] = date('c');

        $json = json_encode(
            $store,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );

        if ($json === false) {

            json_response([
                'success' => false,
                'error' => 'Unable to encode store configuration.'
            ], 400);
        }

        /*
        | Check whether the store already exists.
        */

        $check = $pdo->prepare(
            'SELECT store_id
             FROM store_settings
             WHERE store_id = ?
             LIMIT 1'
        );

        $check->execute([$storeId]);

        $exists = (bool)$check->fetchColumn();

        if ($exists) {

            $stmt = $pdo->prepare(
                'UPDATE store_settings
                 SET store_data = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE store_id = ?'
            );

            $stmt->execute([
                $json,
                $storeId
            ]);

        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO store_settings
                 (
                    store_id,
                    store_data
                 )
                 VALUES (?, ?)'
            );

            $stmt->execute([
                $storeId,
                $json
            ]);
        }

        json_response([
            'success' => true,
            'message' => 'Store settings saved to MySQL.',
            'storeId' => $storeId,
            'store' => $store
        ]);

    } catch (Throwable $e) {

        error_log(
            'Store settings POST error: ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'error' => 'Unable to save store settings.'
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| PUT STORE SETTINGS
|--------------------------------------------------------------------------
| Same behaviour as POST, useful for future admin updates.
|
*/

if ($method === 'PUT') {

    require_admin_token();

    try {

        $payload = request_json();

        $store = $payload['store'] ?? $payload;

        if (!is_array($store)) {

            json_response([
                'success' => false,
                'error' => 'Invalid store configuration.'
            ], 400);
        }

        $store['id'] = $storeId;
        $store['updatedAt'] = date('c');

        $json = json_encode(
            $store,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );

        if ($json === false) {

            json_response([
                'success' => false,
                'error' => 'Unable to encode store configuration.'
            ], 400);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO store_settings
             (
                store_id,
                store_data
             )
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                store_data = VALUES(store_data),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            $storeId,
            $json
        ]);

        json_response([
            'success' => true,
            'message' => 'Store settings updated in MySQL.',
            'storeId' => $storeId,
            'store' => $store
        ]);

    } catch (Throwable $e) {

        error_log(
            'Store settings PUT error: ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'error' => 'Unable to update store settings.'
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| DELETE STORE SETTINGS
|--------------------------------------------------------------------------
| Not normally used by the admin panel.
|
*/

if ($method === 'DELETE') {

    require_admin_token();

    try {

        $stmt = $pdo->prepare(
            'DELETE FROM store_settings
             WHERE store_id = ?'
        );

        $stmt->execute([$storeId]);

        json_response([
            'success' => true,
            'message' => 'Store settings deleted.'
        ]);

    } catch (Throwable $e) {

        error_log(
            'Store settings DELETE error: ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'error' => 'Unable to delete store settings.'
        ], 500);
    }
}


/*
|--------------------------------------------------------------------------
| Unsupported method
|--------------------------------------------------------------------------
*/

json_response([
    'success' => false,
    'error' => 'Method not allowed.'
], 405);