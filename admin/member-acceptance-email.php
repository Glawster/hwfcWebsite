<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';
require_once __DIR__ . '/../members/lib/memberMail.php';

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
    $stmt = $db->prepare('SELECT application_uuid, membership_id, first_name, preferred_name, email, membership_start_date, membership_expiry_date, payment_frequency, status FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member || $member['status'] !== 'Active' || !$member['membership_id']) {
        throw new RuntimeException('Acceptance email can only be sent for an active member.');
    }

    $result = hwfcMembershipSendAcceptance($member);
    hwfcAudit($db, $uuid, 'acceptance_email_' . $result, [
        'membership_id' => $member['membership_id'],
        'mail_mode' => hwfcMembershipMailMode(),
        'actor' => 'admin',
    ]);

    $_SESSION['admin_flash'] = $result === 'sent'
        ? 'Membership acceptance email sent to ' . $member['email'] . '.'
        : 'Membership acceptance email logged for testing; no email was sent.';
} catch (Throwable $e) {
    error_log('Admin membership acceptance email failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
