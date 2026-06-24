<?php
/**
 * Dev router for `php -S` — mirrors the .htaccess mod_rewrite rules.
 * Usage: php -S localhost:1001 -t public_html router.php
 * NOT used in production (Apache handles routing via .htaccess).
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__ . '/public_html';

// Serve existing files (CSS, JS, images, etc.) directly
if ($uri !== '/' && file_exists($root . $uri) && !is_dir($root . $uri)) {
    return false;
}

// Strip trailing slash
$uri = rtrim($uri, '/') ?: '/';

// Try exact .php match (clean URL → .php file)
$candidate = $root . $uri . '.php';
if (file_exists($candidate)) {
    $_SERVER['SCRIPT_NAME']     = $uri . '.php';
    $_SERVER['PHP_SELF']        = $uri . '.php';
    $_SERVER['SCRIPT_FILENAME'] = $candidate;
    require $candidate;
    exit;
}

// Fall through to index.php
require $root . '/index.php';
