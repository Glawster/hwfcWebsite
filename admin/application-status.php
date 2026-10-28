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
$action = trim((string) ($_POST['action'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !in_array($action, ['accept', 'reject'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

$db = hwfcMemberDb();
$successMessage = null;

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT status, membership_id FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();

    if (!$application) {
        throw new RuntimeException('Application not found.');
    }
    if ($application['status'] !== 'Pending') {
        throw new RuntimeException('Only pending applications can be accepted or rejected.');
    }

    $now = gmdate('c');
    if ($action === 'accept') {
        $membershipId = hwfcNextMembershipId($db);
        $update = $db->prepare("UPDATE membership_applications SET status = 'Active', membership_id = :membership_id, updated_at = :updated_at WHERE application_uuid = :uuid AND status = 'Pending'");
        $update->execute([
            ':membership_id' => $membershipId,
            ':updated_at' => $now,
            ':uuid' => $uuid,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Application status changed before it could be accepted.');
        }
        hwfcAudit($db, $uuid, 'application_accepted', [
            'from_status' => 'Pending',
            'to_status' => 'Active',
            'membership_id' => $membershipId,
            'actor' => 'local-development-admin',
        ]);
        $successMessage = 'Application accepted and Membership ID ' . $membershipId . ' assigned.';
    } else {
        $update = $db->prepare("UPDATE membership_applications SET status = 'Rejected', updated_at = :updated_at WHERE application_uuid = :uuid AND status = 'Pending'");
        $update->execute([
            ':updated_at' => $now,
            ':uuid' => $uuid,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Application status changed before it could be rejected.');
        }
        hwfcAudit($db, $uuid, 'application_rejected', [
            'from_status' => 'Pending',
            'to_status' => 'Rejected',
            'actor' => 'local-development-admin',
        ]);
        $successMessage = 'Application rejected.';
    }

    $db->commit();
    $_SESSION['admin_flash'] = $successMessage;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin application status update failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
