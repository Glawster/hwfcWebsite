<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';
require_once __DIR__ . '/../members/lib/memberMail.php';

hwfcRequireAdminDevelopmentAccess();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}

function acceptanceEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$uuid = trim((string) ($_GET['id'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT application_uuid AS applicationUuid, membership_id AS membershipId, first_name AS firstName, last_name AS lastName, preferred_name AS preferredName, email, membership_start_date AS membershipStartDate, membership_expiry_date AS membershipExpiryDate, payment_frequency AS paymentFrequency, status FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member || $member['status'] !== 'Active' || !$member['membershipId']) {
        throw new RuntimeException('Acceptance email is only available for an active member.');
    }

    $config = kitLoadConfig();
    $message = hwfcMembershipAcceptanceMessage($member, $config);

    $sentCheck = $db->prepare("SELECT event_at AS eventAt FROM membership_audit WHERE application_uuid = :uuid AND event_type = 'acceptance_email_sent_manually' ORDER BY id DESC LIMIT 1");
    $sentCheck->execute([':uuid' => $uuid]);
    $sentRecord = $sentCheck->fetch();
} catch (Throwable $e) {
    error_log('Admin membership acceptance preview failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

$name = trim((string) ((($member['preferredName'] ?? '') ?: $member['firstName']) . ' ' . $member['lastName']));
$mailto = 'mailto:' . $message['to'] . '?subject=' . rawurlencode($message['subject']) . '&body=' . rawurlencode($message['body']);
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Membership Acceptance Email | HWFC Administration</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="adminHeader"><div class="adminShell adminHeaderInner"><p class="adminBrand"><strong>HWFC Administration</strong></p><p class="adminMode">Version 0.6a6</p></div></header>
<main class="adminMain"><div class="adminShell">
    <p><a class="textLink" href="/admin/member.php?id=<?= acceptanceEsc($uuid) ?>">← Back To Member</a></p>
    <p class="eyebrow">Membership Onboarding</p>
    <h1>Acceptance Email</h1>

    <section class="adminDetailPanel">
        <h2><?= acceptanceEsc($name) ?></h2>
        <dl class="adminDetailGrid">
            <div><dt>Membership ID</dt><dd><strong><?= acceptanceEsc($member['membershipId']) ?></strong></dd></div>
            <div><dt>Email</dt><dd><?= acceptanceEsc($member['email']) ?></dd></div>
            <div><dt>Membership Start</dt><dd><?= acceptanceEsc($member['membershipStartDate']) ?></dd></div>
            <div><dt>Membership Expiry</dt><dd><?= acceptanceEsc($member['membershipExpiryDate']) ?></dd></div>
            <div><dt>Payment Plan</dt><dd><?= acceptanceEsc($member['paymentFrequency']) ?></dd></div>
        </dl>

        <h3>Email Preview</h3>
        <p><strong><?= acceptanceEsc($message['subject']) ?></strong></p>
        <pre style="white-space:pre-wrap"><?= acceptanceEsc($message['body']) ?></pre>

        <?php if ($sentRecord): ?>
            <div class="adminSuccess">Acceptance email has been marked as sent.</div>
        <?php else: ?>
            <div class="adminActionRow">
                <a class="button buttonPrimary" href="<?= acceptanceEsc($mailto) ?>">Open In Email Client</a>
                <form method="post" action="/admin/memberAcceptanceComplete.php">
                    <input type="hidden" name="csrf" value="<?= acceptanceEsc($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="id" value="<?= acceptanceEsc($uuid) ?>">
                    <button class="button buttonSecondary" type="submit">Mark Email Sent</button>
                </form>
            </div>
            <p class="adminHelp">Open the prepared email, send it from your normal mail client, then mark it sent here so the membership audit record is complete.</p>
        <?php endif; ?>
    </section>
</div></main>
</body>
</html>
