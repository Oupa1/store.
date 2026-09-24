<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
set_cors();
require_method('POST');
require_admin_token();

global $config;
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) json_response(['success'=>false,'error'=>'No valid image upload received.'],422);
$file=$_FILES['image'];
if ($file['size'] > (int)$config['app']['max_upload_bytes']) json_response(['success'=>false,'error'=>'Image exceeds the configured upload limit.'],413);
$info=@getimagesize($file['tmp_name']);
$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
$mime=$info['mime'] ?? '';
if (!$info || !isset($allowed[$mime])) json_response(['success'=>false,'error'=>'Only JPG, PNG, WebP, and GIF images are allowed.'],415);
$dir=$config['app']['upload_dir'];
if (!is_dir($dir) && !mkdir($dir,0755,true)) json_response(['success'=>false,'error'=>'Upload directory is not writable.'],500);
$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
$target=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$name;
if (!move_uploaded_file($file['tmp_name'],$target)) json_response(['success'=>false,'error'=>'Could not save uploaded image.'],500);
$url=rtrim((string)$config['app']['upload_url'],'/').'/'.$name;
json_response(['success'=>true,'url'=>$url,'filename'=>$name,'mime'=>$mime]);
?>
