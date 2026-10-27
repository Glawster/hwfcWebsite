<?php

declare(strict_types=1);

require_once __DIR__ . '/memberConfig.php';

function hwfcMemberDb(): PDO
{
    $path = hwfcMemberDatabasePath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create membership data directory.');
    }

    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = WAL');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_uuid TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    preferred_name TEXT,
    date_of_birth TEXT NOT NULL,
    address TEXT NOT NULL,
    postcode TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    preferred_contact TEXT NOT NULL,
    membership_section TEXT NOT NULL,
    emergency_name TEXT NOT NULL,
    emergency_relationship TEXT NOT NULL,
    emergency_phone TEXT NOT NULL,
    second_emergency_name TEXT,
    second_emergency_phone TEXT,
    health_relevant INTEGER NOT NULL DEFAULT 0,
    health_details TEXT,
    photo_consent INTEGER NOT NULL DEFAULT 0,
    privacy_version TEXT NOT NULL,
    submitted_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pending'
)
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_uuid TEXT NOT NULL,
    event_type TEXT NOT NULL,
    event_at TEXT NOT NULL,
    details TEXT
)
SQL);

    @chmod($path, 0600);
    return $db;
}

function hwfcAudit(PDO $db, string $uuid, string $event, array $details = []): void
{
    $stmt = $db->prepare('INSERT INTO membership_audit (application_uuid, event_type, event_at, details) VALUES (:uuid, :event, :at, :details)');
    $stmt->execute([
        ':uuid' => $uuid,
        ':event' => $event,
        ':at' => gmdate('c'),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
    ]);
}
