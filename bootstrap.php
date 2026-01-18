<?php
/**
 * Project NEXUS - Bootstrap File
 * -------------------------------
 * Loads the application environment and autoloader
 * Used by scripts, cron jobs, and utility files
 */

// Prevent direct access from web
if (php_sapi_name() !== 'cli') {
    // Only allow if called from within the application
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (empty($backtrace[1]['file'])) {
        die('Direct access not permitted');
    }
}

// Determine base directory
$baseDir = __DIR__;

// Load Composer autoloader
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require_once $baseDir . '/vendor/autoload.php';
} else {
    // Manual autoloader fallback (PSR-4)
    spl_autoload_register(function ($class) use ($baseDir) {
        $prefix = 'Nexus\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $baseDir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Load environment variables from .env file
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Start session if not already started (for web-accessible scripts)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load global helper functions
$helpersFile = $baseDir . '/src/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// Resolve tenant context (if needed for multi-tenant operations)
if (class_exists('\Nexus\Core\TenantContext')) {
    \Nexus\Core\TenantContext::resolve();
}
