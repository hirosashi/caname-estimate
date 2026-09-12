<?php
// php -S 用: 実在ファイル（css/js/画像）はそのまま返し、それ以外を index.php へ
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/src' . $path;
if ($path !== '/' && is_file($file) && !str_starts_with($path, '/app/') && !str_starts_with($path, '/config/') && !str_starts_with($path, '/storage/')) {
    return false;
}
require __DIR__ . '/src/index.php';
