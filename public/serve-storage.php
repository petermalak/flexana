<?php
/**
 * Serves files from storage/app/public (uses Laravel so path is always correct).
 * .htaccess rewrites /storage/* to this script with ?path=profiles/xxx.png
 */
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = $_GET['path'] ?? $_GET['laravel_storage_path'] ?? '';
$path = trim(rawurldecode($path));
$path = ltrim(preg_replace('#^storage/#', '', $path), '/');

if ($path === '' || str_contains($path, '..')) {
    http_response_code(404);
    exit('Not found');
}

$disk = \Illuminate\Support\Facades\Storage::disk('public');
if (!$disk->exists($path)) {
    http_response_code(404);
    exit('Not found');
}

$fullPath = $disk->path($path);
$mime = mime_content_type($fullPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
