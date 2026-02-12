<?php
/**
 * Standalone script to serve files from storage/app/public.
 * Use when .htaccess rewrite to index.php loses the path on your server.
 * .htaccess can rewrite: RewriteRule ^ storage/serve-storage.php?path=%1 [L,QSA]
 * Or call directly: /serve-storage.php?path=profiles/xxx.png
 */
$path = $_GET['path'] ?? $_GET['laravel_storage_path'] ?? $_SERVER['REDIRECT_STORAGE_PATH'] ?? $_SERVER['STORAGE_PATH'] ?? '';
$path = ltrim(preg_replace('#^storage/#', '', $path), '/');

if ($path === '' || str_contains($path, '..')) {
    http_response_code(404);
    exit('Not found');
}

$base = dirname(__DIR__) . '/storage/app/public';
$file = $base . '/' . $path;

if (!is_file($file)) {
    http_response_code(404);
    exit('Not found');
}

$mime = mime_content_type($file);
if (!$mime) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
