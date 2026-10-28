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
$paymentType = trim((string) ($_POST['payment_type'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !in_array($paymentType, ['monthly', 'annual'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

function membershipPeriodEnd(string $periodStart): string
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $periodStart);
    if ($start === false) {
        throw new RuntimeException('Membership start date is not valid.');
    }
    return $start->modify('+1 month')->modify('-1 day')->format('Y-m-d');
}

$db = hwfcMemberDb();

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT status, membership_id, membership_start_date, membership_expiry_date, paid_through_date FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member || $member['status'] !== 'Active' || !$member['membership_id']) {
        throw new RuntimeException('Payments can only be recorded for an active member.');
    }
    if (!$member['membership_start_date'] || !$member['membership_expiry_date']) {
        throw new RuntimeException('Set the membership start date before recording a payment.');
    }

    $paymentDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('Y-m-d');
    $expiryDate = (string) $member['membership_expiry_date'];

    if ($paymentType === 'monthly') {
        $periodStart = $member['paid_through_date']
            ? (new DateTimeImmutable((string) $member['paid_through_date']))->modify('+1 day')->format('Y-m-d')
            : (string) $member['membership_start_date'];
        $paidThrough = membershipPeriodEnd($periodStart);
        if ($paidThrough > $expiryDate) {
            $paidThrough = $expiryDate;
        }
        $nextDue = $paidThrough < $expiryDate
            ? (new DateTimeImmutable($paidThrough))->modify('+1 day')->format('Y-m-d')
            : null;
        $amountPence = 500;
        $status = $paidThrough >= $expiryDate ? 'Paid In Full' : 'Current';
        $frequency = 'Monthly';
    } else {
        $paidThrough = $expiryDate;
        $nextDue = null;
        $amountPence = 6000;
        $status = 'Paid In Full';
        $frequency = 'Annual';
    }

    $payment = $db->prepare(<<<'SQL'
INSERT INTO membership_payments (
    application_uuid, membership_id, payment_date, amount_pence,
    payment_type, paid_through_date, recorded_at
) VALUES (
    :uuid, :membership_id, :payment_date, :amount_pence,
    :payment_type, :paid_through_date, :recorded_at
)
SQL);
    $payment->execute([
        ':uuid' => $uuid,
        ':membership_id' => $member['membership_id'],
        ':payment_date' => $paymentDate,
        ':amount_pence' => $amountPence,
        ':payment_type' => $paymentType,
        ':paid_through_date' => $paidThrough,
        ':recorded_at' => gmdate('c'),
    ]);

    $update = $db->prepare(<<<'SQL'
UPDATE membership_applications
SET payment_frequency = :payment_frequency,
    payment_status = :payment_status,
    last_payment_date = :last_payment_date,
    paid_through_date = :paid_through_date,
    next_payment_due_date = :next_payment_due_date,
    updated_at = :updated_at
WHERE application_uuid = :uuid
SQL);
    $update->execute([
        ':payment_frequency' => $frequency,
        ':payment_status' => $status,
        ':last_payment_date' => $paymentDate,
        ':paid_through_date' => $paidThrough,
        ':next_payment_due_date' => $nextDue,
        ':updated_at' => gmdate('c'),
        ':uuid' => $uuid,
    ]);

    hwfcAudit($db, $uuid, 'membership_payment_recorded', [
        'membership_id' => $member['membership_id'],
        'payment_type' => $paymentType,
        'amount_pence' => $amountPence,
        'paid_through_date' => $paidThrough,
        'actor' => 'local-development-admin',
    ]);

    $db->commit();
    $_SESSION['admin_flash'] = $paymentType === 'monthly'
        ? '£5 monthly membership payment recorded. Paid through ' . $paidThrough . '.'
        : '£60 annual membership payment recorded. Membership paid through ' . $paidThrough . '.';
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin membership payment failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
