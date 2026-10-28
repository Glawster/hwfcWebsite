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

$uuid = trim((string) ($_GET['id'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(404);
    exit('Application not found.');
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT application_uuid, membership_id, first_name, last_name, preferred_name, date_of_birth, address, postcode, email, phone, preferred_contact, photo_consent, privacy_version, submitted_at, updated_at, status FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();
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
        <p class="adminMode">Local Development · Version 0.6a2</p>
    </div>
</header>
<main class="adminMain">
    <div class="adminShell">
        <p><a class="textLink" href="/admin/">← Back To Administration</a></p>
        <p class="eyebrow">Membership Application</p>
        <h1><?= adminEsc(trim($application['first_name'] . ' ' . $application['last_name'])) ?></h1>
        <p class="adminMeta">Reference <?= adminEsc(adminReference((string) $application['application_uuid'])) ?> · Status <?= adminEsc($application['status']) ?></p>

        <?php if ($flash): ?><div class="adminSuccess" role="status"><?= adminEsc($flash) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="adminError" role="alert"><?= adminEsc($error) ?></div><?php endif; ?>

        <?php if ($application['status'] === 'Pending'): ?>
        <section class="adminActionPanel" aria-labelledby="applicationActionsHeading">
            <h2 id="applicationActionsHeading">Application Actions</h2>
            <p>Accepting assigns the next permanent HWFC Membership ID. Rejecting records the application as rejected and does not issue a Membership ID.</p>
            <div class="adminActionRow">
                <form method="post" action="/admin/application-status.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="accept">
                    <button class="button buttonPrimary" type="submit">Accept Application</button>
                </form>
                <form method="post" action="/admin/application-status.php">
                    <input type="hidden" name="csrf" value="<?= adminEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= adminEsc($uuid) ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="button buttonSecondary" type="submit">Reject Application</button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section class="adminDetailPanel">
            <h2>Member Details</h2>
            <dl class="adminDetailGrid">
                <?php if ($application['membership_id']): ?><div><dt>Membership ID</dt><dd><strong><?= adminEsc($application['membership_id']) ?></strong></dd></div><?php endif; ?>
                <div><dt>First Name</dt><dd><?= adminEsc($application['first_name']) ?></dd></div>
                <div><dt>Last Name</dt><dd><?= adminEsc($application['last_name']) ?></dd></div>
                <div><dt>Preferred Name</dt><dd><?= adminEsc($application['preferred_name'] ?: '—') ?></dd></div>
                <div><dt>Date Of Birth</dt><dd><?= adminEsc($application['date_of_birth']) ?></dd></div>
                <div class="adminDetailWide"><dt>Home Address</dt><dd><?= nl2br(adminEsc($application['address'])) ?></dd></div>
                <div><dt>Postcode</dt><dd><?= adminEsc($application['postcode']) ?></dd></div>
                <div><dt>Telephone</dt><dd><?= adminEsc($application['phone']) ?></dd></div>
                <div><dt>Email</dt><dd><?= adminEsc($application['email']) ?></dd></div>
                <div><dt>Preferred Contact</dt><dd><?= adminEsc(ucfirst((string) $application['preferred_contact'])) ?></dd></div>
            </dl>
        </section>

        <section class="adminDetailPanel">
            <h2>Privacy And Submission</h2>
            <dl class="adminDetailGrid">
                <div><dt>Photography / Publicity Consent</dt><dd><?= ((int) $application['photo_consent']) === 1 ? 'Yes' : 'No' ?></dd></div>
                <div><dt>Privacy Notice Version</dt><dd><?= adminEsc($application['privacy_version']) ?></dd></div>
                <div><dt>Submitted</dt><dd><?= adminEsc(adminDate((string) $application['submitted_at'])) ?></dd></div>
                <div><dt>Last Updated</dt><dd><?= adminEsc(adminDate((string) $application['updated_at'])) ?></dd></div>
            </dl>
        </section>

        <div class="adminNotice">Emergency-contact and health information will be exposed here only after authenticated admin access is implemented.</div>
    </div>
</main>
</body>
</html>
