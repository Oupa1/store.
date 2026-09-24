<?php
declare(strict_types=1);
// Usage: php scripts/import_store_json.php /path/to/store_data.json
require_once __DIR__ . '/../bootstrap.php';
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }
$path=$argv[1] ?? '';
if (!$path || !is_file($path)) { fwrite(STDERR, "Usage: php scripts/import_store_json.php /path/to/store_data.json\n"); exit(1); }
$data=json_decode(file_get_contents($path),true);
if (!is_array($data) || !is_array($data['products'] ?? null)) { fwrite(STDERR, "Expected a JSON object with a products array.\n"); exit(1); }
$pdo=db(); $pdo->beginTransaction();
try {
  foreach ($data['products'] as $p) {
    $id=(string)($p['id'] ?? bin2hex(random_bytes(12))); $inv=$p['inventory']??[];
    $q=$pdo->prepare('INSERT INTO products (id,store_id,name,slug,sku,brand,description,short_description,price,sale_price,cost_price,status,inventory_quantity,track_stock,allow_backorder,low_stock_threshold,is_featured,is_new_arrival,is_best_seller,rating,review_count,seo_json,tags_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),sku=VALUES(sku),brand=VALUES(brand),description=VALUES(description),short_description=VALUES(short_description),price=VALUES(price),sale_price=VALUES(sale_price),cost_price=VALUES(cost_price),status=VALUES(status),inventory_quantity=VALUES(inventory_quantity),track_stock=VALUES(track_stock),allow_backorder=VALUES(allow_backorder),low_stock_threshold=VALUES(low_stock_threshold),is_featured=VALUES(is_featured),is_new_arrival=VALUES(is_new_arrival),is_best_seller=VALUES(is_best_seller),rating=VALUES(rating),review_count=VALUES(review_count),seo_json=VALUES(seo_json),tags_json=VALUES(tags_json)');
    $q->execute([$id,store_id(),$p['name']??'Untitled',$p['slug']??$id,$p['sku']??$id,$p['brand']??null,$p['description']??null,$p['shortDescription']??null,(float)($p['price']??0),isset($p['salePrice'])?(float)$p['salePrice']:null,isset($p['costPrice'])?(float)$p['costPrice']:null,$p['status']??'active',(int)($inv['quantity']??0),!empty($inv['trackStock']),!empty($inv['allowBackorder']),(int)($inv['lowStockThreshold']??3),!empty($p['isFeatured']),!empty($p['isNewArrival']),!empty($p['isBestSeller']),(float)($p['rating']??0),(int)($p['reviewCount']??0),json_encode($p['seo']??[]),json_encode($p['tags']??[])]);
    $pdo->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$id]); $qi=$pdo->prepare('INSERT INTO product_images(product_id,image_url,sort_order,is_primary) VALUES(?,?,?,?)'); foreach (($p['images']??[]) as $i=>$url) $qi->execute([$id,$url,$i,$i===0]);
    $pdo->prepare('DELETE FROM product_options WHERE product_id=?')->execute([$id]); $qo=$pdo->prepare('INSERT IGNORE INTO product_options(product_id,option_name,option_value,sort_order) VALUES(?,?,?,?)'); foreach (($p['attributes']??[]) as $attr) foreach (($attr['selectedOptions']??[]) as $i=>$val) $qo->execute([$id,$attr['name']??$attr['attributeId']??'Option',$val,$i]);
    $pdo->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$id]); $qv=$pdo->prepare('INSERT INTO product_variants(id,product_id,sku,size_value,colour_value,attributes_json,price,sale_price,stock,image_url) VALUES(?,?,?,?,?,?,?,?,?,?)'); foreach (($p['variants']??[]) as $v) { $a=$v['attributes']??[]; $qv->execute([$v['id']??bin2hex(random_bytes(12)),$id,$v['sku']??$id,$a['Size']??null,$a['Colour']??($a['Color']??null),json_encode($a),(float)($v['price']??$p['price']??0),isset($v['salePrice'])?(float)$v['salePrice']:null,(int)($v['stock']??0),$v['imageUrl']??null]); }
    $pdo->prepare('DELETE FROM product_variation_images WHERE product_id=?')->execute([$id]); $qvi=$pdo->prepare('INSERT INTO product_variation_images(product_id,colour_value,image_url) VALUES(?,?,?)'); foreach (($p['variationImages']??[]) as $colour=>$url) $qvi->execute([$id,$colour,$url]);
  }
  $pdo->commit(); echo "Imported ".count($data['products'])." products.\n";
} catch (Throwable $e) { $pdo->rollBack(); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
