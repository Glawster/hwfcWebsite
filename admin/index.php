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
$error = null;

try {
    $db = hwfcMemberDb();
    $applications = $db->query(<<<'SQL'
SELECT application_uuid, first_name, last_name, email, phone, status, submitted_at
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
        <p class="adminMode">Local Development · Read Only · Version 0.6a2</p>
    </div>
</header>
<main class="adminMain">
    <div class="adminShell">
        <p class="eyebrow">Administration Dashboard</p>
        <h1>Club Administration</h1>
        <p class="lead">This development view reads the same membership database used by the public application form. No changes can be made from this page yet.</p>

        <div class="adminSummaryGrid" aria-label="Membership application summary">
            <div class="adminSummaryCard"><span>Total Applications</span><strong><?= $counts['total'] ?></strong></div>
            <div class="adminSummaryCard"><span>Pending</span><strong><?= $counts['pending'] ?></strong></div>
            <div class="adminSummaryCard"><span>Active</span><strong><?= $counts['active'] ?></strong></div>
            <div class="adminSummaryCard"><span>Lapsed</span><strong><?= $counts['lapsed'] ?></strong></div>
            <div class="adminSummaryCard"><span>Rejected</span><strong><?= $counts['rejected'] ?></strong></div>
        </div>

        <section class="adminPanel" aria-labelledby="membershipApplicationsHeading">
            <div class="adminPanelHeader">
                <h2 id="membershipApplicationsHeading">Membership Applications</h2>
                <p>Read-only summary. Sensitive health and emergency-contact information is deliberately excluded from this list.</p>
            </div>
            <?php if ($error !== null): ?>
                <div class="adminError" role="alert"><?= adminEsc($error) ?></div>
            <?php elseif (!$applications): ?>
                <div class="adminEmpty">No membership applications have been submitted to this database yet.</div>
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
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td><?= adminEsc(adminReference((string) $application['application_uuid'])) ?></td>
                                <td><?= adminEsc(trim($application['first_name'] . ' ' . $application['last_name'])) ?></td>
                                <td><?= adminEsc($application['email']) ?></td>
                                <td><?= adminEsc($application['phone']) ?></td>
                                <td><span class="adminStatus"><?= adminEsc($application['status']) ?></span></td>
                                <td><?= adminEsc(adminDate((string) $application['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="futureAdminHeading">
            <div class="sectionHeading"><div><p class="eyebrow">Next Admin Functions</p><h2 id="futureAdminHeading">Planned Work</h2></div></div>
            <div class="adminPlaceholderGrid">
                <article class="adminPlaceholder"><h3>Member Management</h3><p>Review applications, accept/reject, assign Membership IDs, maintain membership periods and payment status.</p></article>
                <article class="adminPlaceholder"><h3>Kit Orders</h3><p>Track payment, supplier ordering, receipt and member handover against the existing kit-order reference.</p></article>
                <article class="adminPlaceholder"><h3>Data Protection</h3><p>Authenticated subject-access export, correction and deletion/anonymisation workflows building on the existing GDPR tooling.</p></article>
                <article class="adminPlaceholder"><h3>Configuration</h3><p>Protected management of club settings already defined by the kit and membership requirements.</p></article>
            </div>
        </section>
    </div>
</main>
</body>
</html>
