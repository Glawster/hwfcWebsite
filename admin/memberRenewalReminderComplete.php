<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';

hwfcRequireAdminDevelopmentAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$csrf = (string) ($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
    http_response_code(400);
    exit('Invalid admin form session.');
}

$uuid = trim((string) ($_POST['id'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT status, membership_id AS membershipId, email FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member || $member['status'] !== 'Lapsed' || !$member['membershipId']) {
        throw new RuntimeException('Renewal reminder status can only be recorded for a lapsed member.');
    }

    hwfcAudit($db, $uuid, 'renewal_reminder_sent_manually', [
        'membershipId' => $member['membershipId'],
        'actor' => 'admin',
    ]);

    $_SESSION['admin_flash'] = 'Renewal reminder marked as sent to ' . $member['email'] . '.';
} catch (Throwable $e) {
    error_log('Admin renewal reminder completion failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
