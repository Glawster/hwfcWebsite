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
if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !in_array($action, ['accept', 'reject', 'undoAcceptance', 'undoRejection'], true)) {
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

    $now = gmdate('c');

    if ($action === 'accept') {
        if ($application['status'] !== 'Pending') {
            throw new RuntimeException('Only pending applications can be accepted.');
        }
        $membershipId = $application['membership_id'] ?: hwfcNextMembershipId($db);
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
            'fromStatus' => 'Pending',
            'toStatus' => 'Active',
            'membershipId' => $membershipId,
            'actor' => 'local-development-admin',
        ]);
        $successMessage = 'Application accepted and Membership ID ' . $membershipId . ' assigned.';
    } elseif ($action === 'reject') {
        if ($application['status'] !== 'Pending') {
            throw new RuntimeException('Only pending applications can be rejected.');
        }
        $update = $db->prepare("UPDATE membership_applications SET status = 'Rejected', updated_at = :updated_at WHERE application_uuid = :uuid AND status = 'Pending'");
        $update->execute([
            ':updated_at' => $now,
            ':uuid' => $uuid,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Application status changed before it could be rejected.');
        }
        hwfcAudit($db, $uuid, 'application_rejected', [
            'fromStatus' => 'Pending',
            'toStatus' => 'Rejected',
            'actor' => 'local-development-admin',
        ]);
        $successMessage = 'Application rejected.';
    } elseif ($action === 'undoAcceptance') {
        if ($application['status'] !== 'Active') {
            throw new RuntimeException('Only active applications can have acceptance undone.');
        }
        $releasedMembershipId = (string) ($application['membership_id'] ?? '');
        $update = $db->prepare("UPDATE membership_applications SET status = 'Pending', membership_id = NULL, membership_start_date = NULL, membership_expiry_date = NULL, payment_frequency = 'Not Set', payment_status = 'Not Recorded', last_payment_date = NULL, paid_through_date = NULL, next_payment_due_date = NULL, updated_at = :updated_at WHERE application_uuid = :uuid AND status = 'Active'");
        $update->execute([
            ':updated_at' => $now,
            ':uuid' => $uuid,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Application status changed before acceptance could be undone.');
        }
        hwfcAudit($db, $uuid, 'application_acceptance_undone', [
            'fromStatus' => 'Active',
            'toStatus' => 'Pending',
            'releasedMembershipId' => $releasedMembershipId,
            'membershipDetailsCleared' => true,
            'actor' => 'local-development-admin',
        ]);
        $successMessage = $releasedMembershipId !== ''
            ? 'Acceptance undone. Membership ID ' . $releasedMembershipId . ' released for reuse.'
            : 'Acceptance undone.';
    } else {
        if ($application['status'] !== 'Rejected') {
            throw new RuntimeException('Only rejected applications can have rejection undone.');
        }
        $update = $db->prepare("UPDATE membership_applications SET status = 'Pending', updated_at = :updated_at WHERE application_uuid = :uuid AND status = 'Rejected'");
        $update->execute([
            ':updated_at' => $now,
            ':uuid' => $uuid,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Application status changed before rejection could be undone.');
        }
        hwfcAudit($db, $uuid, 'application_rejection_undone', [
            'fromStatus' => 'Rejected',
            'toStatus' => 'Pending',
            'actor' => 'local-development-admin',
        ]);
        $successMessage = 'Rejection undone. Application returned to Pending.';
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
