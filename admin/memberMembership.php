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
$startDate = trim((string) ($_POST['membershipStartDate'] ?? ''));
$expiryDate = trim((string) ($_POST['membershipExpiryDate'] ?? ''));
$paymentFrequency = trim((string) ($_POST['paymentFrequency'] ?? 'Not Set'));
$paymentStatus = trim((string) ($_POST['paymentStatus'] ?? 'Not Recorded'));
$lastPaymentDate = trim((string) ($_POST['lastPaymentDate'] ?? ''));
$paidThroughDate = trim((string) ($_POST['paidThroughDate'] ?? ''));

if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    exit('Invalid request.');
}

$allowedPaymentFrequencies = ['Not Set', 'Annual', 'Monthly'];
if (!in_array($paymentFrequency, $allowedPaymentFrequencies, true)) {
    http_response_code(400);
    exit('Invalid payment frequency.');
}

$allowedPaymentStatuses = ['Not Recorded', 'Current', 'Payment Due', 'Overdue', 'Paid In Full', 'Waived'];
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

function annualExpiryFromStart(string $startDate): string
{
    return (new DateTimeImmutable($startDate))->modify('+1 year -1 day')->format('Y-m-d');
}

function nextPaymentDueFromPaidThrough(string $paidThroughDate): string
{
    return (new DateTimeImmutable($paidThroughDate))->modify('+1 day')->format('Y-m-d');
}

if (!validAdminDate($startDate) || !validAdminDate($expiryDate) || !validAdminDate($lastPaymentDate) || !validAdminDate($paidThroughDate)) {
    $_SESSION['admin_error'] = 'Membership and payment dates must be valid dates.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

if ($startDate !== '' && $expiryDate === '') {
    $expiryDate = annualExpiryFromStart($startDate);
}

if ($startDate !== '' && $expiryDate !== '' && $expiryDate < $startDate) {
    $_SESSION['admin_error'] = 'Membership expiry date cannot be before the start date.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

if ($paidThroughDate !== '' && $startDate !== '' && $paidThroughDate < $startDate) {
    $_SESSION['admin_error'] = 'Paid-through date cannot be before the membership start date.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

if ($paymentFrequency === 'Annual' && $paymentStatus === 'Paid In Full' && $paidThroughDate === '' && $expiryDate !== '') {
    $paidThroughDate = $expiryDate;
}

$nextPaymentDueDate = $paidThroughDate !== '' ? nextPaymentDueFromPaidThrough($paidThroughDate) : '';
$redirectToAcceptancePreview = false;

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT status, membership_id AS membershipId, membership_start_date AS membershipStartDate, membership_expiry_date AS membershipExpiryDate, payment_frequency AS paymentFrequency FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();

    if (!$application) {
        throw new RuntimeException('Application not found.');
    }
    if ($application['status'] !== 'Active' || !$application['membershipId']) {
        throw new RuntimeException('Membership details can only be maintained for an active member.');
    }

    $wasOnboarded = $application['membershipStartDate']
        && $application['membershipExpiryDate']
        && in_array($application['paymentFrequency'], ['Annual', 'Monthly'], true);

    $update = $db->prepare(<<<'SQL'
UPDATE membership_applications
SET membership_start_date = :start_date,
    membership_expiry_date = :expiry_date,
    payment_frequency = :payment_frequency,
    payment_status = :payment_status,
    last_payment_date = :last_payment_date,
    paid_through_date = :paid_through_date,
    next_payment_due_date = :next_payment_due_date,
    updated_at = :updated_at
WHERE application_uuid = :uuid
SQL);
    $update->execute([
        ':start_date' => $startDate !== '' ? $startDate : null,
        ':expiry_date' => $expiryDate !== '' ? $expiryDate : null,
        ':payment_frequency' => $paymentFrequency,
        ':payment_status' => $paymentStatus,
        ':last_payment_date' => $lastPaymentDate !== '' ? $lastPaymentDate : null,
        ':paid_through_date' => $paidThroughDate !== '' ? $paidThroughDate : null,
        ':next_payment_due_date' => $nextPaymentDueDate !== '' ? $nextPaymentDueDate : null,
        ':updated_at' => gmdate('c'),
        ':uuid' => $uuid,
    ]);

    hwfcAudit($db, $uuid, 'membership_details_updated', [
        'membershipId' => $application['membershipId'],
        'paymentFrequency' => $paymentFrequency,
        'paymentStatus' => $paymentStatus,
        'actor' => 'admin',
    ]);

    $isReadyForOnboarding = $startDate !== ''
        && $expiryDate !== ''
        && in_array($paymentFrequency, ['Annual', 'Monthly'], true);

    $redirectToAcceptancePreview = !$wasOnboarded && $isReadyForOnboarding;
    $_SESSION['admin_flash'] = 'Membership and payment details saved.';
} catch (Throwable $e) {
    error_log('Admin membership details update failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

if ($redirectToAcceptancePreview) {
    header('Location: /admin/memberAcceptanceEmail.php?id=' . rawurlencode($uuid));
    exit;
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
