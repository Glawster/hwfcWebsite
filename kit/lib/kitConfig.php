<?php
declare(strict_types=1);

function kitLoadConfig(): array
{
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2));
    if (!$root) throw new RuntimeException('Website document root is unavailable.');

    $path = getenv('HWFC_KIT_CONFIG');
    if (!$path) $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'kitConfig.json';
    if (!is_file($path)) throw new RuntimeException('Kit configuration is missing.');

    kitValidateConfigPath($path, $root);
    $config = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $config = kitResolveStorageDir($config);
    kitValidateConfig($config);
    return $config;
}

/** The live JSON config may live only in /config beneath the webroot. */
function kitValidateConfigPath(string $path, string $root): void
{
    $resolved = realpath($path);
    $expectedDir = realpath($root . DIRECTORY_SEPARATOR . 'config');
    if (!$resolved || !$expectedDir || dirname($resolved) !== $expectedDir) {
        throw new RuntimeException('Kit configuration must use the protected webroot config directory.');
    }

    $guard = $expectedDir . DIRECTORY_SEPARATOR . '.htaccess';
    $guardText = is_file($guard) ? (string) file_get_contents($guard) : '';
    if (!str_contains($guardText, 'Require all denied')) {
        throw new RuntimeException('Webroot kit configuration is not protected from HTTP access.');
    }
}

/** Resolve a relative storageDir beneath the webroot. */
function kitResolveStorageDir(array $config): array
{
    $storage = $config['storageDir'] ?? null;
    if (!is_string($storage) || trim($storage) === '') return $config;
    if (str_starts_with($storage, DIRECTORY_SEPARATOR)) return $config;

    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2));
    if (!$root) throw new RuntimeException('Website document root is unavailable.');
    $resolved = realpath($root . DIRECTORY_SEPARATOR . ltrim($storage, '/\\'));
    if (!$resolved) throw new RuntimeException('Order storage directory does not exist.');
    $config['storageDir'] = $resolved;
    return $config;
}

/** Orders may live in /orders beneath the webroot when protected by Apache. */
function kitValidateOrderStoragePath(string $path): void
{
    $resolved = realpath($path);
    if (!$resolved) throw new RuntimeException('Order storage directory is unavailable.');

    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2));
    if (!$root || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) return;

    $expected = realpath($root . DIRECTORY_SEPARATOR . 'orders');
    if (!$expected || $resolved !== $expected) {
        throw new RuntimeException('Webroot order storage must use the protected orders directory.');
    }

    $guard = $resolved . DIRECTORY_SEPARATOR . '.htaccess';
    $guardText = is_file($guard) ? (string) file_get_contents($guard) : '';
    if (!str_contains($guardText, 'Require all denied')) {
        throw new RuntimeException('Webroot order storage is not protected from HTTP access.');
    }
}

function kitValidateConfig(array $c): void
{
    foreach (['managerEmail', 'fromEmail'] as $key) {
        if (!is_string($c[$key] ?? null) || !filter_var($c[$key], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $c[$key])) {
            throw new RuntimeException('Invalid mail configuration.');
        }
    }

    $w = $c['window'] ?? [];
    foreach (['id', 'opens', 'closes'] as $key) {
        if (!is_string($w[$key] ?? null) || trim($w[$key]) === '') throw new RuntimeException('Invalid kit window.');
    }
    foreach (['opens', 'closes'] as $key) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $w[$key])) throw new RuntimeException('Window dates require explicit timezone offsets.');
        $date = new DateTimeImmutable($w[$key]);
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new RuntimeException('Invalid window date.');
    }
    if (new DateTimeImmutable($w['opens']) >= new DateTimeImmutable($w['closes'])) throw new RuntimeException('Invalid window range.');

    if (!is_string($c['storageDir'] ?? null) || !is_dir($c['storageDir']) || !is_writable($c['storageDir'])) throw new RuntimeException('Order storage is unavailable.');
    kitValidateOrderStoragePath($c['storageDir']);

    foreach (['accountName', 'sortCode', 'accountNumber'] as $key) {
        if (!is_string($c['bank'][$key] ?? null) || trim($c['bank'][$key]) === '') throw new RuntimeException('Bank-transfer configuration is incomplete.');
    }

    if (!is_int($c['deliveryPence'] ?? null) || $c['deliveryPence'] < 0 || $c['deliveryPence'] > 100000) throw new RuntimeException('Invalid delivery charge.');
    $allowedAddress = ['recipient', 'address1', 'address2', 'city', 'county', 'postcode'];
    if (!is_array($c['deliveryRequired'] ?? null) || array_diff($c['deliveryRequired'], $allowedAddress)) throw new RuntimeException('Invalid required delivery fields.');
    if (array_diff(['address1', 'city', 'postcode'], $c['deliveryRequired'])) throw new RuntimeException('Delivery requires address, town and postcode.');

    if (!is_array($c['products'] ?? null) || count($c['products']) < 1 || count($c['products']) > 20) throw new RuntimeException('Kit catalogue is unavailable.');
    foreach ($c['products'] as $id => $p) {
        if (!is_string($id) || !preg_match('/^[a-z][A-Za-z0-9]{0,39}$/', $id) || !is_array($p)) {
            throw new RuntimeException('Invalid product identifier: ' . (is_scalar($id) ? (string) $id : '[non-scalar]'));
        }
        foreach (['name', 'description'] as $key) if (!is_string($p[$key] ?? null) || $p[$key] === '') throw new RuntimeException('Invalid product text.');
        foreach (['pricePence', 'initialsPence'] as $key) if (!is_int($p[$key] ?? null) || $p[$key] < 0 || $p[$key] > 100000) throw new RuntimeException('Invalid product charge.');
        foreach (['initials', 'freeShirt'] as $key) if (!is_bool($p[$key] ?? null)) throw new RuntimeException('Invalid product rule.');
        if (!is_array($p['sizes'] ?? null) || !$p['sizes']) throw new RuntimeException('Missing sizes.');
        foreach ($p['sizes'] as $size) if (!is_string($size) || $size === '' || strlen($size) > 40) throw new RuntimeException('Invalid size.');
        if (!is_int($p['maxQuantity'] ?? null) || $p['maxQuantity'] < 1 || $p['maxQuantity'] > 99) throw new RuntimeException('Invalid quantity limit.');
        if (isset($p['image']) && (!is_string($p['image']) || !preg_match('#^/assets/[a-zA-Z0-9/_.-]+$#', $p['image']) || str_contains($p['image'], '..'))) throw new RuntimeException('Images must use local asset paths.');
    }
}

function kitWindowState(array $c, DateTimeImmutable $now): string
{
    if ($now < new DateTimeImmutable($c['window']['opens'])) return 'before';
    return $now < new DateTimeImmutable($c['window']['closes']) ? 'open' : 'closed';
}

function kitMoney(int $pence): string { return '£' . number_format($pence / 100, 2); }
function kitDate(string $date): string { return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Europe/London'))->format('j F Y, H:i T'); }
