<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$privatePrefixes = [
    '/config',
    '/orders',
    '/members/lib',
    '/members/bin',
    '/members/data',
    '/admin/lib',
];

foreach ($privatePrefixes as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        http_response_code(404);
        exit;
    }
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;

return false;
