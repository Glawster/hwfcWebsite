<?php

declare(strict_types=1);

function hwfcMemberDatabasePath(): string
{
    $configured = getenv('HWFC_MEMBER_DB');
    if (is_string($configured) && trim($configured) !== '') {
        return $configured;
    }

    return dirname(__DIR__) . '/data/members.sqlite';
}

function hwfcPrivacyVersion(): string
{
    return '2026-10-27';
}
