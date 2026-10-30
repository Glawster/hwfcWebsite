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

function adminAuditColumns(?string $json): array
{
    $columns = ['from' => [], 'to' => [], 'who' => []];
    $details = json_decode((string) $json, true);
    if (!is_array($details)) {
        if ($json !== null && $json !== '') {
            $columns['to']['Recorded details'] = $json;
        }
        return $columns;
    }
    foreach ($details as $key => $value) {
        $key = (string) $key;
        $column = 'to';
        if (in_array($key, ['actor', 'who'], true)) {
            $column = 'who';
        } elseif (preg_match('/^from(?:_|[A-Z]|$)|(?:Before|_before)$/', $key)) {
            $column = 'from';
        }
        $label = preg_replace('/^(?:from|to)(?:_|(?=[A-Z]))|(?:Before|After|_before|_after)$/', '', $key);
        $label = ucfirst(str_replace('_', ' ', (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', (string) $label)));
        $columns[$column][$label] = is_bool($value) ? ($value ? 'Yes' : 'No')
            : (is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    return $columns;
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

$allowedTabs = ['details', 'dates', 'payments', 'sessions', 'notes', 'history', 'dataProtection'];
$activeTab = trim((string) ($_GET['tab'] ?? 'details'));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'details';
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
       form_answers AS formAnswers,
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

    $auditHistory = [];
    if ($activeTab === 'history') {
        $auditStmt = $db->prepare('SELECT event_type, event_at, details FROM membership_audit WHERE application_uuid = :uuid ORDER BY event_at DESC, id DESC');
        $auditStmt->execute([':uuid' => $uuid]);
        $auditHistory = $auditStmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log('Admin membership detail failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The membership database could not be read.');
}

if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}

function memberTabUrl(string $uuid, string $tab): string
{
    return '/admin/member.php?id=' . rawurlencode($uuid) . '&tab=' . rawurlencode($tab);
}
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Details | HWFC Administration</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="adminHeader">
    <div class="adminShell adminHeaderInner">
        <p class="adminBrand"><strong>HWFC Administration</strong></p>
        <p class="adminMode">Local Development · Version 0.6a5</p>
    </div>
</header>
<main class="adminMain">
    <div class="adminShell adminTabbedMember">
        <p><a class="textLink" href="/admin/">← Back To Administration</a></p>
        <p class="eyebrow">Member Record</p>
        <h1><?= adminEsc(trim($application['firstName'] . ' ' . $application['lastName'])) ?></h1>
        <p class="adminMeta">Reference <?= adminEsc(adminReference((string) $application['applicationUuid'])) ?> · Status <?= adminEsc($application['status']) ?><?php if ($application['membershipId']): ?> · <?= adminEsc($application['membershipId']) ?><?php endif; ?></p>

        <?php if ($flash): ?><div class="adminSuccess" role="status"><?= adminEsc($flash) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="adminError" role="alert"><?= adminEsc($error) ?></div><?php endif; ?>

        <nav class="adminMemberTabs" aria-label="Member record sections">
            <?php foreach ([
                'details' => 'Member Details',
                'dates' => 'Date Details',
                'payments' => 'Payment Details',
                'sessions' => 'Session Details',
                'notes' => 'Notes',
                'history' => 'History',
                'dataProtection' => 'Data Protection',
            ] as $tabKey => $tabLabel): ?>
                <a href="<?= adminEsc(memberTabUrl($uuid, $tabKey)) ?>" class="adminMemberTab<?= $activeTab === $tabKey ? ' adminMemberTabActive' : '' ?>"<?= $activeTab === $tabKey ? ' aria-current="page"' : '' ?>><?= adminEsc($tabLabel) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="adminMemberTabContent">
        <?php if ($activeTab === 'details'): ?>
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
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="undoAcceptance">
                    <button class="button buttonSecondary" type="submit">Undo Acceptance</button>
                </form>
            </section>
            <?php elseif ($application['status'] === 'Rejected'): ?>
            <section class="adminActionPanel" aria-labelledby="applicationActionsHeading">
                <h2 id="applicationActionsHeading">Application Actions</h2>
                <p>If this application was rejected by mistake, undoing rejection returns it to Pending so it can be reviewed again.</p>
                <form method="post" action="/admin/applicationStatus.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="undoRejection">
                    <button class="button buttonSecondary" type="submit">Undo Rejection</button>
                </form>
            </section>
            <?php endif; ?>

            <section class="adminDetailPanel">
                <h2>Member Details</h2>
                <dl class="adminDetailGrid adminMemberDetailGrid">
                    <div><dt>First Name</dt><dd><?= adminEsc($application['firstName']) ?></dd></div>
                    <div><dt>Last Name</dt><dd><?= adminEsc($application['lastName']) ?></dd></div>
                    <div><dt>Preferred Name</dt><dd><?= adminEsc($application['preferredName'] ?: '—') ?></dd></div>
                    <?php if ($application['membershipId']): ?><div><dt>Membership ID</dt><dd><strong><?= adminEsc($application['membershipId']) ?></strong></dd></div><?php endif; ?>
                    <div class="adminDetailRowStart adminMemberDob"><dt>Date Of Birth</dt><dd><?= adminEsc(adminDisplayDate($application['dateOfBirth'])) ?></dd></div>
                    <div class="adminDetailSpanThree adminDetailRowStart adminMemberAddress"><dt>Home Address</dt><dd><?= nl2br(adminEsc($application['address'])) ?></dd></div>
                    <div class="adminMemberPostcode"><dt>Postcode</dt><dd><?= adminEsc($application['postcode']) ?></dd></div>
                    <div class="adminDetailRowStart adminMemberEmail"><dt>Email</dt><dd><?= adminEsc($application['email']) ?></dd></div>
                    <div class="adminMemberTelephone"><dt>Telephone</dt><dd><?= adminEsc($application['phone']) ?></dd></div>
                    <div class="adminMemberContact"><dt>Preferred Contact</dt><dd><?= adminEsc(match ((string) $application['preferredContact']) {
                        'whatsapp' => 'WhatsApp',
                        'text' => 'Text Message',
                        'phone' => 'Phone Call',
                        default => ucfirst((string) $application['preferredContact']),
                    }) ?></dd></div>
                </dl>
            </section>

            <section class="adminDetailPanel" aria-labelledby="formAnswersHeading">
                <h2 id="formAnswersHeading">Form Answers</h2>
                <?php if (trim((string) ($application['formAnswers'] ?? '')) === ''): ?>
                    <p class="adminHelp">No member responses form answers are recorded for this member.</p>
                <?php else: ?>
                    <p class="adminFormAnswers"><?= nl2br(adminEsc($application['formAnswers'])) ?></p>
                    <p class="adminHelp">Answers from the member responses Google Form. Change them by importing an edited export with update mode on the Member Import/Export page.</p>
                <?php endif; ?>
            </section>

            <div class="adminNotice">Emergency-contact and health information will be exposed here only after authenticated admin access is implemented.</div>

        <?php elseif ($activeTab === 'dates'): ?>
            <section class="adminDetailPanel">
                <h2>Date Details</h2>
                <?php if (!$application['membershipId']): ?>
                    <p class="adminHelp">Membership dates become available after the application is accepted and a Membership ID is assigned.</p>
                <?php else: ?>
                    <dl class="adminDetailGrid">
                        <div><dt>Membership Status</dt><dd><?= adminEsc($application['status']) ?></dd></div>
                        <div><dt>Member Since</dt><dd><?= adminEsc(adminDisplayDate($application['membershipStartDate'])) ?></dd></div>
                        <div><dt>Renewal Date</dt><dd><?= adminEsc(adminRenewalDate($application['membershipExpiryDate'])) ?></dd></div>
                        <div><dt>Expiry Date</dt><dd><?= adminEsc(adminDisplayDate($application['membershipExpiryDate'])) ?></dd></div>
                        <div><dt>Last Payment</dt><dd><?= adminEsc(adminDisplayDate($application['lastPaymentDate'])) ?></dd></div>
                        <div><dt>Paid Through</dt><dd><?= adminEsc(adminDisplayDate($application['paidThroughDate'])) ?></dd></div>
                        <div><dt>Next Payment Due</dt><dd><?= adminEsc(adminDisplayDate($application['nextPaymentDueDate'])) ?></dd></div>
                    </dl>
                    <?php if ($application['status'] === 'Lapsed' && filter_var($application['email'], FILTER_VALIDATE_EMAIL)): ?>
                        <div class="adminActionRow adminSectionAction">
                            <a class="button buttonPrimary" href="/admin/memberRenewalReminder.php?id=<?= adminEsc(rawurlencode($uuid)) ?>">Send Renewal Reminder</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

        <?php elseif ($activeTab === 'payments'): ?>
            <section class="adminDetailPanel">
                <h2>Payment Details</h2>
                <?php if (!$application['membershipId']): ?>
                    <p class="adminHelp">Payment details become available after the application is accepted and a Membership ID is assigned.</p>
                <?php else: ?>
                    <dl class="adminDetailGrid">
                        <div><dt>Payment Plan</dt><dd><?= adminEsc($application['paymentFrequency'] ?: 'Not Set') ?></dd></div>
                        <div><dt>Payment Status</dt><dd><?= adminEsc($application['paymentStatus'] ?: 'Not Recorded') ?></dd></div>
                        <div class="adminDetailRowStart"><dt>Last Payment</dt><dd><?= adminEsc(adminDisplayDate($application['lastPaymentDate'])) ?></dd></div>
                        <div><dt>Paid Through</dt><dd><?= adminEsc(adminDisplayDate($application['paidThroughDate'])) ?></dd></div>
                        <div><dt>Next Payment Due</dt><dd><?= adminEsc(adminDisplayDate($application['nextPaymentDueDate'])) ?></dd></div>
                    </dl>

                    <?php if ($application['status'] === 'Active'): ?>
                        <h3>Membership And Payment Settings</h3>
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
                            <p class="adminHelp">Membership expiry is the end of the annual membership period. For monthly payments, Paid Through records how far payments currently cover the member; Next Payment Due is calculated as the following day.</p>
                            <button class="button buttonPrimary" type="submit">Save Membership Details</button>
                        </form>

                        <h3>Record Payment</h3>
                        <p class="adminHelp">Use these after confirming the bank transfer.</p>
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
                    <?php endif; ?>

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
                <?php endif; ?>
            </section>

        <?php elseif ($activeTab === 'dataProtection'): ?>
            <section class="adminDetailPanel">
                <h2>Data Protection</h2>
                <p>Manage this member's privacy choices and data protection requests.</p>
                <p><a class="textLink" href="/privacy/">View Club Privacy Notice →</a></p>
                <h3>Subject Access Requests</h3>
                <p>Prepare the information held about this member for review and disclosure. Data export currently uses the club's protected administration command-line tool.</p>
                <h3>Data Correction</h3>
                <p>Record requests to correct inaccurate personal information. Corrections currently use the protected administration command-line tool.</p>
                <h3>Retention And Erasure</h3>
                <p>Review the reason for retaining this member's information before processing a deletion request. Deletion currently uses the protected administration command-line tool; browser deletion and anonymisation are not yet available.</p>
                <p class="adminHelp">Browser controls for these requests will be housed here as they become available.</p>
            </section>
            <section class="adminDetailPanel">
                <h2>Privacy And Consent</h2>
                <form method="post" action="/admin/memberPhotoConsent.php" class="adminInlinePreferenceForm">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <label>Photography / Publicity Consent
                        <select name="photoConsent">
                            <option value="1"<?= ((int) $application['photoConsent']) === 1 ? ' selected' : '' ?>>Yes</option>
                            <option value="0"<?= ((int) $application['photoConsent']) === 0 ? ' selected' : '' ?>>No</option>
                        </select>
                    </label>
                    <button class="button buttonPrimary" type="submit">Update Consent</button>
                </form>
                <dl class="adminDetailGrid">
                    <div><dt>Privacy Notice Version</dt><dd><?= adminEsc($application['privacyVersion']) ?></dd></div>
                    <div><dt>Submitted</dt><dd><?= adminEsc(adminDate((string) $application['submittedAt'])) ?></dd></div>
                    <div><dt>Last Updated</dt><dd><?= adminEsc(adminDate((string) $application['updatedAt'])) ?></dd></div>
                </dl>
            </section>

        <?php elseif ($activeTab === 'history'): ?>
            <section class="adminDetailPanel">
                <h2>History</h2>
                <p class="adminHelp">All recorded audit events for this member, newest first. Events that were not recorded cannot be shown.</p>
                <?php if (!$auditHistory): ?>
                    <p>No audit history has been recorded for this member.</p>
                <?php else: ?>
                    <div class="adminTableWrap">
                        <table class="adminTable">
                            <thead><tr><th scope="col">Date And Time</th><th scope="col">Event</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Who</th></tr></thead>
                            <tbody>
                            <?php foreach ($auditHistory as $event): ?>
                                <tr>
                                    <td><?= adminEsc(adminDate((string) $event['event_at'])) ?></td>
                                    <td><?= adminEsc(ucwords(str_replace('_', ' ', (string) $event['event_type']))) ?></td>
                                    <?php foreach (adminAuditColumns($event['details']) as $column => $values): ?>
                                    <td>
                                        <?php if ($values): ?>
                                            <dl class="adminAuditDetails">
                                                <?php foreach ($values as $label => $value): ?>
                                                    <?php if ($column !== 'who'): ?><dt><?= adminEsc($label) ?></dt><?php endif; ?>
                                                    <dd><?= adminEsc($value) ?></dd>
                                                <?php endforeach; ?>
                                            </dl>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($activeTab === 'notes'): ?>
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

        <?php elseif ($activeTab === 'sessions'): ?>
            <section class="adminDetailPanel">
                <h2>Session Details</h2>
                <div class="adminDataNotice">
                    Individual member session attendance is not currently stored by the website. organiseMyFooty imports only aggregate attendance totals for session-income reconciliation, so there is no reliable per-member session history to display yet.
                </div>
                <p><a class="textLink" href="/admin/sessionTakings.php">Open Session Takings →</a></p>
            </section>
        <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
