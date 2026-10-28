<?php

declare(strict_types=1);

function hwfcAdminDevelopmentAccessAllowed(): bool
{
    $enabled = getenv('HWFC_ADMIN_DEV_MODE') === '1';
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $loopback = in_array($remote, ['127.0.0.1', '::1'], true);

    return $enabled && $loopback;
}

function hwfcRequireAdminDevelopmentAccess(): void
{
    if (hwfcAdminDevelopmentAccessAllowed()) {
        return;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found.\n");
}
