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
$photoConsent = trim((string) ($_POST['photoConsent'] ?? ''));

if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !in_array($photoConsent, ['0', '1'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT photo_consent AS photoConsent FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member) {
        throw new RuntimeException('Member not found.');
    }

    $previousConsent = (int) $member['photoConsent'];
    $newConsent = (int) $photoConsent;

    if ($previousConsent !== $newConsent) {
        $update = $db->prepare('UPDATE membership_applications SET photo_consent = :photoConsent, updated_at = :updatedAt WHERE application_uuid = :uuid');
        $update->execute([
            ':photoConsent' => $newConsent,
            ':updatedAt' => gmdate('c'),
            ':uuid' => $uuid,
        ]);

        hwfcAudit($db, $uuid, 'photo_consent_updated', [
            'fromConsent' => $previousConsent === 1,
            'toConsent' => $newConsent === 1,
            'actor' => 'admin',
        ]);
    }

    $_SESSION['admin_flash'] = $newConsent === 1
        ? 'Photography / publicity consent recorded.'
        : 'Photography / publicity consent withdrawn.';
} catch (Throwable $e) {
    error_log('Admin photo consent update failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid) . '&tab=dataProtection');
exit;
