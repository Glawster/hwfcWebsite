<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/config' || str_starts_with($path, '/config/') || $path === '/orders' || str_starts_with($path, '/orders/')) {
    http_response_code(404);
    exit;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;

return false;
