<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';

hwfcRequireAdminDevelopmentAccess();

$applications = [];
$counts = [
    'total' => 0,
    'pending' => 0,
    'active' => 0,
    'lapsed' => 0,
    'rejected' => 0,
];
$sessionIncomePence = 0;
$error = null;

try {
    $db = hwfcMemberDb();
    $applications = $db->query(<<<'SQL'
SELECT application_uuid AS applicationUuid,
       membership_id AS membershipId,
       first_name AS firstName,
       last_name AS lastName,
       email,
       phone,
       status,
       submitted_at AS submittedAt
FROM membership_applications
ORDER BY submitted_at DESC, id DESC
SQL)->fetchAll();

    foreach ($applications as $application) {
        $counts['total']++;
        $key = strtolower((string) $application['status']);
        if (array_key_exists($key, $counts)) {
            $counts[$key]++;
        }
    }

    $sessionIncomePence = (int) $db->query('SELECT COALESCE(SUM(total_pence), 0) FROM session_takings')->fetchColumn();
} catch (Throwable $e) {
    $error = 'The membership database could not be read. Check HWFC_MEMBER_DB and SQLite support.';
    error_log('Admin membership dashboard failed: ' . $e->getMessage());
}

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

function adminMoney(int $pence): string
{
    return '£' . number_format($pence / 100, 2);
}
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration | Hillsborough Walking Football Club</title>
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
        <p class="eyebrow">Administration Dashboard</p>
        <h1>Club Administration</h1>
        <p class="lead">Manage member details, membership payments and lightweight club session income records.</p>

        <div class="adminSummaryGrid" aria-label="Membership summary">
            <div class="adminSummaryCard"><span>Member Records</span><strong><?= $counts['total'] ?></strong></div>
            <div class="adminSummaryCard"><span>Pending</span><strong><?= $counts['pending'] ?></strong></div>
            <div class="adminSummaryCard"><span>Active</span><strong><?= $counts['active'] ?></strong></div>
            <div class="adminSummaryCard"><span>Lapsed</span><strong><?= $counts['lapsed'] ?></strong></div>
            <div class="adminSummaryCard"><span>Rejected</span><strong><?= $counts['rejected'] ?></strong></div>
            <div class="adminSummaryCard"><span>Session Income Recorded</span><strong><?= adminEsc(adminMoney($sessionIncomePence)) ?></strong></div>
        </div>

        <section class="adminPanel" aria-labelledby="memberDetailsHeading">
            <div class="adminPanelHeader">
                <h2 id="memberDetailsHeading">Member Details</h2>
                <p>Select any member record to open their details. Sensitive health and emergency-contact information remains excluded until authenticated admin access is implemented.</p>
            </div>
            <?php if ($error !== null): ?>
                <div class="adminError" role="alert"><?= adminEsc($error) ?></div>
            <?php elseif (!$applications): ?>
                <div class="adminEmpty">No member records have been submitted to this database yet.</div>
            <?php else: ?>
                <div class="adminTableWrap">
                    <table class="adminTable">
                        <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Telephone</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Membership ID</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($applications as $application): ?>
                            <?php $detailUrl = '/admin/member.php?id=' . rawurlencode((string) $application['applicationUuid']); ?>
                            <tr class="adminClickableRow" data-href="<?= adminEsc($detailUrl) ?>" tabindex="0" role="link" aria-label="Open <?= adminEsc(trim($application['firstName'] . ' ' . $application['lastName'])) ?>">
                                <td><?= adminEsc(adminReference((string) $application['applicationUuid'])) ?></td>
                                <td><a class="adminTableLink" href="<?= adminEsc($detailUrl) ?>"><?= adminEsc(trim($application['firstName'] . ' ' . $application['lastName'])) ?></a></td>
                                <td><?= adminEsc($application['email']) ?></td>
                                <td><?= adminEsc($application['phone']) ?></td>
                                <td><span class="adminStatus"><?= adminEsc($application['status']) ?></span></td>
                                <td><?= adminEsc(adminDate((string) $application['submittedAt'])) ?></td>
                                <td><?= adminEsc($application['membershipId'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="adminToolsHeading">
            <div class="sectionHeading"><div><p class="eyebrow">Administration</p><h2 id="adminToolsHeading">Admin Tools</h2></div></div>
            <div class="adminPlaceholderGrid">
                <article class="adminPlaceholder"><h3>Session Takings</h3><p>Import member attendance totals from organiseMyFooty and reconcile £2 session income.</p><p><a class="textLink" href="/admin/sessionTakings.php">Open Session Takings →</a></p></article>
                <article class="adminPlaceholder"><h3>Member Payments</h3><p>Open an active member above to record £5 monthly or £60 annual membership payments and view their payment history.</p></article>
            </div>
        </section>

        <section aria-labelledby="futureAdminHeading">
            <div class="sectionHeading"><div><p class="eyebrow">Next Admin Functions</p><h2 id="futureAdminHeading">Planned Work</h2></div></div>
            <div class="adminPlaceholderGrid">
                <article class="adminPlaceholder"><h3>Kit Orders</h3><p>Track payment, supplier ordering, receipt and member handover against the existing kit-order reference.</p></article>
                <article class="adminPlaceholder"><h3>Data Protection</h3><p>Authenticated subject-access export, correction and deletion/anonymisation workflows building on the existing GDPR tooling.</p></article>
                <article class="adminPlaceholder"><h3>Configuration</h3><p>Protected management of club settings already defined by the kit and membership requirements.</p></article>
            </div>
        </section>
    </div>
</main>
<script>
document.querySelectorAll('.adminClickableRow').forEach((row) => {
    const openRow = () => { window.location.href = row.dataset.href; };
    row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea')) return;
        openRow();
    });
    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openRow();
        }
    });
});
</script>
</body>
</html>
