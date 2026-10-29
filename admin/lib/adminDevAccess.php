<?php

declare(strict_types=1);

function hwfcAdminDevelopmentAccessAllowed(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $loopback = in_array($remote, ['127.0.0.1', '::1'], true);
    $localDevelopment = getenv('HWFC_ADMIN_DEV_MODE') === '1' && $loopback;

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $stagingHost = $host === 'staging.hillsboroughwalkingfootball.com';
    $stagingAccess = getenv('HWFC_ENV') === 'staging'
        && getenv('HWFC_ADMIN_STAGING_MODE') === '1'
        && $stagingHost;

    return $localDevelopment || $stagingAccess;
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
