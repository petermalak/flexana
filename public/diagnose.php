<?php
/**
 * Laravel Application Diagnostic Script
 * 
 * This script helps diagnose common issues causing HTTP 500 errors.
 * Access it at: https://sdhds.net/backend/public/diagnose.php
 * 
 * IMPORTANT: Remove this file after troubleshooting for security!
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Laravel Application Diagnostic ===\n\n";

// 1. Check PHP Version
echo "1. PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    echo "   ❌ ERROR: PHP 8.2+ required\n";
} else {
    echo "   ✅ OK\n";
}

// 2. Check if vendor directory exists
echo "\n2. Vendor Directory: ";
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ EXISTS\n";
} else {
    echo "❌ MISSING - Run: composer install\n";
}

// 3. Check .env file
echo "\n3. .env File: ";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    echo "✅ EXISTS\n";
    
    // Check APP_KEY
    $envContent = file_get_contents($envPath);
    if (strpos($envContent, 'APP_KEY=') !== false && strpos($envContent, 'APP_KEY=base64:') !== false) {
        echo "   APP_KEY: ✅ SET\n";
    } else {
        echo "   APP_KEY: ❌ MISSING - Run: php artisan key:generate\n";
    }
} else {
    echo "❌ MISSING - Copy from .env.example\n";
}

// 4. Check storage permissions
echo "\n4. Storage Permissions: ";
$storagePath = __DIR__ . '/../storage';
if (is_writable($storagePath)) {
    echo "✅ WRITABLE\n";
} else {
    echo "❌ NOT WRITABLE - Run: chmod -R 775 storage\n";
}

// 5. Check bootstrap/cache permissions
echo "\n5. Bootstrap Cache Permissions: ";
$cachePath = __DIR__ . '/../bootstrap/cache';
if (is_writable($cachePath)) {
    echo "✅ WRITABLE\n";
} else {
    echo "❌ NOT WRITABLE - Run: chmod -R 775 bootstrap/cache\n";
}

// 6. Check required PHP extensions
echo "\n6. Required PHP Extensions:\n";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'fileinfo', 'tokenizer', 'xml', 'ctype', 'bcmath'];
$missing = [];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ $ext\n";
    } else {
        echo "   ❌ $ext - MISSING\n";
        $missing[] = $ext;
    }
}

// 7. Try to load Laravel
echo "\n7. Laravel Bootstrap: ";
try {
    if (file_exists($vendorPath)) {
        require $vendorPath;
        $app = require_once __DIR__ . '/../bootstrap/app.php';
        echo "✅ SUCCESS\n";
        
        // Try to get config
        echo "\n8. Configuration:\n";
        try {
            $config = $app->make('config');
            echo "   APP_NAME: " . ($config->get('app.name', 'NOT SET')) . "\n";
            echo "   APP_ENV: " . ($config->get('app.env', 'NOT SET')) . "\n";
            echo "   APP_DEBUG: " . ($config->get('app.debug') ? 'true' : 'false') . "\n";
            echo "   APP_URL: " . ($config->get('app.url', 'NOT SET')) . "\n";
        } catch (\Exception $e) {
            echo "   ❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        // Try database connection
        echo "\n9. Database Connection: ";
        try {
            $db = $app->make('db');
            $db->connection()->getPdo();
            echo "✅ CONNECTED\n";
        } catch (\Exception $e) {
            echo "❌ FAILED: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ FAILED - vendor/autoload.php not found\n";
    }
} catch (\Exception $e) {
    echo "❌ FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 8. Check file paths
echo "\n10. File Paths:\n";
echo "   Document Root: " . __DIR__ . "\n";
echo "   Application Root: " . dirname(__DIR__) . "\n";
echo "   Storage Path: " . ($storagePath ?? 'N/A') . "\n";

// 9. Check web server
echo "\n11. Web Server Info:\n";
echo "   Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "   Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
echo "   Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";
echo "   Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";

echo "\n=== Diagnostic Complete ===\n";
echo "\n⚠️  REMEMBER: Delete this file after troubleshooting!\n";
