<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';

hwfcRequireAdminDevelopmentAccess();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}
$flash = $_SESSION['admin_flash'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_flash'], $_SESSION['admin_error']);

function adminEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminReference(string $uuid): string
{
    return strtoupper(substr($uuid, 0, 10));
}

function adminDate(string $iso): string
{
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Europe/London'))->format('d M Y H:i');
    } catch (Throwable) {
        return $iso;
    }
}

function adminDisplayDate(?string $iso): string
{
    $value = trim((string) $iso);
    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable) {
        return $value;
    }
}

function adminRenewalDate(?string $expiryDate): string
{
    $value = trim((string) $expiryDate);
    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->modify('+1 day')->format('d M Y');
    } catch (Throwable) {
        return $value;
    }
}

function adminMoney(int $pence): string
{
    return '£' . number_format($pence / 100, 2);
}

$uuid = trim((string) ($_GET['id'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(404);
    exit('Application not found.');
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare(<<<'SQL'
SELECT application_uuid AS applicationUuid,
       membership_id AS membershipId,
       first_name AS firstName,
       last_name AS lastName,
       preferred_name AS preferredName,
       date_of_birth AS dateOfBirth,
       address,
       postcode,
       email,
       phone,
       preferred_contact AS preferredContact,
       photo_consent AS photoConsent,
       privacy_version AS privacyVersion,
       admin_notes AS adminNotes,
       membership_start_date AS membershipStartDate,
       membership_expiry_date AS membershipExpiryDate,
       payment_frequency AS paymentFrequency,
       payment_status AS paymentStatus,
       last_payment_date AS lastPaymentDate,
       paid_through_date AS paidThroughDate,
       next_payment_due_date AS nextPaymentDueDate,
       submitted_at AS submittedAt,
       updated_at AS updatedAt,
       status
FROM membership_applications
WHERE application_uuid = :uuid
LIMIT 1
SQL);
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();

    $paymentStmt = $db->prepare(<<<'SQL'
SELECT payment_date AS paymentDate,
       amount_pence AS amountPence,
       payment_type AS paymentType,
       paid_through_date AS paidThroughDate,
       recorded_at AS recordedAt
FROM membership_payments
WHERE application_uuid = :uuid
ORDER BY payment_date DESC, id DESC
SQL);
    $paymentStmt->execute([':uuid' => $uuid]);
    $paymentHistory = $paymentStmt->fetchAll();
} catch (Throwable $e) {
    error_log('Admin membership detail failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The membership database could not be read.');
}

if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Membership Application | HWFC Administration</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="adminHeader">
    <div class="adminShell adminHeaderInner">
        <p class="adminBrand"><strong>HWFC Administration</strong></p>
        <p class="adminMode">Local Development · Version 0.6a3</p>
    </div>
</header>
<main class="adminMain">
    <div class="adminShell">
        <p><a class="textLink" href="/admin/">← Back To Administration</a></p>
        <p class="eyebrow">Membership Application</p>
        <h1><?= adminEsc(trim($application['firstName'] . ' ' . $application['lastName'])) ?></h1>
        <p class="adminMeta">Reference <?= adminEsc(adminReference((string) $application['applicationUuid'])) ?> · Status <?= adminEsc($application['status']) ?></p>

        <?php if ($flash): ?><div class="adminSuccess" role="status"><?= adminEsc($flash) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="adminError" role="alert"><?= adminEsc($error) ?></div><?php endif; ?>

        <?php if ($application['status'] === 'Pending'): ?>
        <section class="adminActionPanel" aria-labelledby="applicationActionsHeading">
            <h2 id="applicationActionsHeading">Application Actions</h2>
            <p>Accepting assigns the next available HWFC Membership ID. Rejecting records the application as rejected and does not issue a Membership ID.</p>
            <div class="adminActionRow">
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="accept">
                    <button class="button buttonPrimary" type="submit">Accept Application</button>
                </form>
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="button buttonSecondary" type="submit">Reject Application</button>
                </form>
            </div>
        </section>
        <?php elseif ($application['status'] === 'Active' && $application['membershipId']): ?>
        <section class="adminActionPanel" aria-labelledby="applicationActionsHeading">
            <h2 id="applicationActionsHeading">Application Actions</h2>
            <p>If this application was accepted by mistake, undoing acceptance returns it to Pending and releases Membership ID <?= adminEsc($application['membershipId']) ?> for reuse.</p>
            <div class="adminActionRow">
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="undoAcceptance">
                    <button class="button buttonSecondary" type="submit">Undo Acceptance</button>
                </form>
            </div>
        </section>
        <?php elseif ($application['status'] === 'Rejected'): ?>
        <section class="adminActionPanel" aria-labelledby="applicationActionsHeading">
            <h2 id="applicationActionsHeading">Application Actions</h2>
            <p>If this application was rejected by mistake, undoing rejection returns it to Pending so it can be reviewed again.</p>
            <div class="adminActionRow">
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="undoRejection">
                    <button class="button buttonSecondary" type="submit">Undo Rejection</button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section class="adminDetailPanel">
            <h2>Member Details</h2>
            <dl class="adminDetailGrid adminMemberDetailGrid">
                <?php if ($application['membershipId']): ?><div class="adminDetailWide"><dt>Membership ID</dt><dd><strong><?= adminEsc($application['membershipId']) ?></strong></dd></div><?php endif; ?>
                <div><dt>First Name</dt><dd><?= adminEsc($application['firstName']) ?></dd></div>
                <div><dt>Last Name</dt><dd><?= adminEsc($application['lastName']) ?></dd></div>
                <div><dt>Preferred Name</dt><dd><?= adminEsc($application['preferredName'] ?: '—') ?></dd></div>
                <div><dt>Date Of Birth</dt><dd><?= adminEsc(adminDisplayDate($application['dateOfBirth'])) ?></dd></div>
                <div class="adminDetailSpanTwo"><dt>Home Address</dt><dd><?= nl2br(adminEsc($application['address'])) ?></dd></div>
                <div><dt>Postcode</dt><dd><?= adminEsc($application['postcode']) ?></dd></div>
                <div><dt>Email</dt><dd><?= adminEsc($application['email']) ?></dd></div>
                <div><dt>Telephone</dt><dd><?= adminEsc($application['phone']) ?></dd></div>
                <div><dt>Preferred Contact</dt><dd><?= adminEsc(ucfirst((string) $application['preferredContact'])) ?></dd></div>
            </dl>
        </section>

        <?php if ($application['membershipId']): ?>
        <section class="adminDetailPanel">
            <h2>Membership Dates</h2>
            <dl class="adminDetailGrid">
                <div><dt>Membership Status</dt><dd><?= adminEsc($application['status']) ?></dd></div>
                <div><dt>Member Since</dt><dd><?= adminEsc(adminDisplayDate($application['membershipStartDate'])) ?></dd></div>
                <div><dt>Renewal Date</dt><dd><?= adminEsc(adminRenewalDate($application['membershipExpiryDate'])) ?></dd></div>
                <div><dt>Expiry Date</dt><dd><?= adminEsc(adminDisplayDate($application['membershipExpiryDate'])) ?></dd></div>
                <div><dt>Payment Plan</dt><dd><?= adminEsc($application['paymentFrequency'] ?: 'Not Set') ?></dd></div>
                <div><dt>Payment Status</dt><dd><?= adminEsc($application['paymentStatus'] ?: 'Not Recorded') ?></dd></div>
                <div><dt>Last Payment</dt><dd><?= adminEsc(adminDisplayDate($application['lastPaymentDate'])) ?></dd></div>
                <div><dt>Paid Through</dt><dd><?= adminEsc(adminDisplayDate($application['paidThroughDate'])) ?></dd></div>
                <div><dt>Next Payment Due</dt><dd><?= adminEsc(adminDisplayDate($application['nextPaymentDueDate'])) ?></dd></div>
            </dl>
            <?php if ($application['status'] === 'Lapsed' && filter_var($application['email'], FILTER_VALIDATE_EMAIL)): ?>
                <div class="adminActionRow" style="margin-top:1rem">
                    <a class="button buttonPrimary" href="/admin/memberRenewalReminder.php?id=<?= adminEsc(rawurlencode($uuid)) ?>">Send Renewal Reminder</a>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($application['status'] === 'Active' && $application['membershipId']): ?>
        <section class="adminDetailPanel">
            <h2>Membership And Payment</h2>
            <form method="post" action="/admin/memberMembership.php" class="adminMembershipForm">
                <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                <div class="adminFormGrid">
                    <label>Membership Start Date
                        <input type="date" name="membershipStartDate" value="<?= adminEsc($application['membershipStartDate'] ?? '') ?>">
                    </label>
                    <label>Membership Expiry Date
                        <input type="date" name="membershipExpiryDate" value="<?= adminEsc($application['membershipExpiryDate'] ?? '') ?>">
                    </label>
                    <label>Payment Frequency
                        <select name="paymentFrequency">
                            <?php foreach (['Not Set', 'Annual', 'Monthly'] as $frequencyOption): ?>
                                <option value="<?= adminEsc($frequencyOption) ?>"<?= $application['paymentFrequency'] === $frequencyOption ? ' selected' : '' ?>><?= adminEsc($frequencyOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Payment Status
                        <select name="paymentStatus">
                            <?php foreach (['Not Recorded', 'Current', 'Payment Due', 'Overdue', 'Paid In Full', 'Waived'] as $paymentOption): ?>
                                <option value="<?= adminEsc($paymentOption) ?>"<?= $application['paymentStatus'] === $paymentOption ? ' selected' : '' ?>><?= adminEsc($paymentOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Last Payment Date
                        <input type="date" name="lastPaymentDate" value="<?= adminEsc($application['lastPaymentDate'] ?? '') ?>">
                    </label>
                    <label>Paid Through
                        <input type="date" name="paidThroughDate" value="<?= adminEsc($application['paidThroughDate'] ?? '') ?>">
                    </label>
                    <label>Next Payment Due
                        <input type="date" value="<?= adminEsc($application['nextPaymentDueDate'] ?? '') ?>" readonly>
                    </label>
                </div>
                <p class="adminHelp">Membership expiry is the end of the annual membership period. For monthly payments, Paid Through records how far payments currently cover the member; Next Payment Due is calculated as the following day. If an expiry date is left blank when a start date is saved, it is set automatically to one year less one day.</p>
                <button class="button buttonPrimary" type="submit">Save Membership Details</button>
            </form>

            <h3>Record Payment</h3>
            <p class="adminHelp">Use these after confirming the bank transfer. The monthly action advances Paid Through by one membership month; the annual action covers the member through their membership expiry.</p>
            <div class="adminActionRow">
                <form method="post" action="/admin/memberPayment.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="paymentType" value="monthly">
                    <button class="button buttonPrimary" type="submit">Record £5 Monthly Payment</button>
                </form>
                <form method="post" action="/admin/memberPayment.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="paymentType" value="annual">
                    <button class="button buttonSecondary" type="submit">Record £60 Annual Payment</button>
                </form>
            </div>

            <h3>Payment History</h3>
            <?php if (!$paymentHistory): ?>
                <p class="adminHelp">No membership payments have been recorded yet.</p>
            <?php else: ?>
                <div class="adminTableWrap">
                    <table class="adminTable">
                        <thead><tr><th>Payment Date</th><th>Type</th><th>Amount</th><th>Paid Through</th><th>Recorded</th></tr></thead>
                        <tbody>
                        <?php foreach ($paymentHistory as $payment): ?>
                            <tr>
                                <td><?= adminEsc(adminDisplayDate($payment['paymentDate'])) ?></td>
                                <td><?= adminEsc(ucfirst((string) $payment['paymentType'])) ?></td>
                                <td><?= adminEsc(adminMoney((int) $payment['amountPence'])) ?></td>
                                <td><?= adminEsc(adminDisplayDate($payment['paidThroughDate'])) ?></td>
                                <td><?= adminEsc(adminDate((string) $payment['recordedAt'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="adminDetailPanel">
            <h2>Notes</h2>
            <form method="post" action="/admin/memberNotes.php" class="adminNotesForm">
                <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                <label for="adminNotes">Internal free-form notes</label>
                <textarea id="adminNotes" name="adminNotes" maxlength="5000" rows="6"><?= adminEsc($application['adminNotes'] ?? '') ?></textarea>
                <p class="adminHelp">For club administration notes only. Avoid recording unnecessary sensitive information.</p>
                <button class="button buttonPrimary" type="submit">Save Notes</button>
            </form>
        </section>

        <section class="adminDetailPanel">
            <h2>Privacy And Submission</h2>
            <dl class="adminDetailGrid">
                <div><dt>Photography / Publicity Consent</dt><dd><?= ((int) $application['photoConsent']) === 1 ? 'Yes' : 'No' ?></dd></div>
                <div><dt>Privacy Notice Version</dt><dd><?= adminEsc($application['privacyVersion']) ?></dd></div>
                <div><dt>Submitted</dt><dd><?= adminEsc(adminDate((string) $application['submittedAt'])) ?></dd></div>
                <div><dt>Last Updated</dt><dd><?= adminEsc(adminDate((string) $application['updatedAt'])) ?></dd></div>
            </dl>
        </section>

        <div class="adminNotice">Emergency-contact and health information will be exposed here only after authenticated admin access is implemented.</div>
    </div>
</main>
</body>
</html>
