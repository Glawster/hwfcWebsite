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
$startDate = trim((string) ($_POST['membership_start_date'] ?? ''));
$expiryDate = trim((string) ($_POST['membership_expiry_date'] ?? ''));
$paymentStatus = trim((string) ($_POST['payment_status'] ?? 'Not Recorded'));
$lastPaymentDate = trim((string) ($_POST['last_payment_date'] ?? ''));

if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    exit('Invalid request.');
}

$allowedPaymentStatuses = ['Not Recorded', 'Current', 'Overdue', 'Paid In Full', 'Waived'];
if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
    http_response_code(400);
    exit('Invalid payment status.');
}

function validAdminDate(string $value): bool
{
    if ($value === '') {
        return true;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

if (!validAdminDate($startDate) || !validAdminDate($expiryDate) || !validAdminDate($lastPaymentDate)) {
    $_SESSION['admin_error'] = 'Membership dates must be valid dates.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

if ($startDate !== '' && $expiryDate !== '' && $expiryDate < $startDate) {
    $_SESSION['admin_error'] = 'Membership expiry date cannot be before the start date.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT status, membership_id, membership_start_date, membership_expiry_date, payment_status, last_payment_date FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();

    if (!$application) {
        throw new RuntimeException('Application not found.');
    }
    if ($application['status'] !== 'Active' || !$application['membership_id']) {
        throw new RuntimeException('Membership details can only be maintained for an active member.');
    }

    $update = $db->prepare(<<<'SQL'
UPDATE membership_applications
SET membership_start_date = :start_date,
    membership_expiry_date = :expiry_date,
    payment_status = :payment_status,
    last_payment_date = :last_payment_date,
    updated_at = :updated_at
WHERE application_uuid = :uuid
SQL);
    $update->execute([
        ':start_date' => $startDate !== '' ? $startDate : null,
        ':expiry_date' => $expiryDate !== '' ? $expiryDate : null,
        ':payment_status' => $paymentStatus,
        ':last_payment_date' => $lastPaymentDate !== '' ? $lastPaymentDate : null,
        ':updated_at' => gmdate('c'),
        ':uuid' => $uuid,
    ]);

    hwfcAudit($db, $uuid, 'membership_details_updated', [
        'membership_id' => $application['membership_id'],
        'payment_status' => $paymentStatus,
        'actor' => 'local-development-admin',
    ]);

    $_SESSION['admin_flash'] = 'Membership details saved.';
} catch (Throwable $e) {
    error_log('Admin membership details update failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
