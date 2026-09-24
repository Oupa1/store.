# Poder Emporium PHP/MySQL Product Backend

This package provides persistent product management for a standard PHP/MySQL host. It stores products, manually entered sizes and colours, product galleries, variants, and colour-to-image mappings in MySQL. Uploaded product images are stored on the server instead of browser local storage.

## Included files

| File | Purpose |
|---|---|
| `schema.sql` | Creates the product, option, variant, image, and admin tables. |
| `config.example.php` | Database and upload configuration template. Copy it to `config.php`. |
| `bootstrap.php` | PDO connection, JSON helpers, CORS, and admin-token protection. |
| `api/products.php` | Public product reads plus protected create, update, and delete endpoints. |
| `api/upload.php` | Protected JPG/PNG/WebP/GIF image upload endpoint. |
| `.htaccess` | Protects configuration files and blocks uploaded PHP execution. |

## Installation through cPanel/phpMyAdmin

1. Create a MySQL database and database user in cPanel. Grant the user **all privileges** on that database.
2. Open phpMyAdmin, select the new database, choose **Import**, and import `schema.sql`.
3. Upload the package into a private folder or an API folder under `public_html`, for example `public_html/poder-api/`.
4. Copy `config.example.php` to `config.php` and edit the database host, database name, username, password, and `store_id`.
5. Set `upload_url` to the public URL path that maps to the package upload folder. If the package is at `public_html/poder-api`, use `/poder-api/uploads/products`.
6. Set a long random `admin_token` in `config.php`. Send it in the `X-Admin-Token` request header for write operations.
7. Ensure `uploads/products` is writable by PHP, normally permissions `755` or `775`. Do not use `777` unless your host explicitly requires it.

## API examples

List products:

```text
GET /poder-api/api/products.php
```

Get one product:

```text
GET /poder-api/api/products.php?id=PRODUCT_ID
```

Create or update a product with JSON:

```json
{
  "name": "Signature Oxford",
  "sku": "PE-OXF-001",
  "price": 1899,
  "status": "active",
  "images": ["/poder-api/uploads/products/cover.jpg"],
  "attributes": [
    {"name": "Size", "selectedOptions": ["XS", "S", "M", "L", "XL", "XXL"]},
    {"name": "Colour", "selectedOptions": ["Red", "Black"]}
  ],
  "variationImages": {
    "Red": "/poder-api/uploads/products/red.jpg",
    "Black": "/poder-api/uploads/products/black.jpg"
  },
  "inventory": {"quantity": 10, "trackStock": true, "allowBackorder": false, "lowStockThreshold": 3},
  "variants": [
    {"sku":"PE-OXF-001-RED-M","attributes":{"Size":"M","Colour":"Red"},"price":1899,"stock":4}
  ]
}
```

Use `POST /api/products.php` to create and `PUT /api/products.php?id=PRODUCT_ID` to update. Include `X-Admin-Token: YOUR_RANDOM_TOKEN`. Use `DELETE /api/products.php?id=PRODUCT_ID` to delete.

Upload an image with multipart form field `image`:

```bash
curl -X POST \
  -H 'X-Admin-Token: YOUR_RANDOM_TOKEN' \
  -F 'image=@red-shoe.jpg' \
  https://your-domain.co.za/poder-api/api/upload.php
```

The response contains a permanent server URL. Save that URL in `variationImages` under the matching colour.

## Important security notes

Do not place the database password in frontend JavaScript. Do not expose `config.php`. Configure the admin token before using write endpoints. For a production admin dashboard, replace the shared token with PHP session login and CSRF protection; the included token is a practical integration bridge, not a complete identity system.

The existing static storefront still needs a frontend integration change to call these endpoints instead of browser-only local storage. This backend package is ready for that integration and can also be used immediately by an admin panel or API client.
