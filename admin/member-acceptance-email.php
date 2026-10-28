<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';
require_once __DIR__ . '/../members/lib/memberMail.php';

hwfcRequireAdminDevelopmentAccess();

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
    $stmt = $db->prepare('SELECT application_uuid, membership_id, first_name, last_name, preferred_name, email, membership_start_date, membership_expiry_date, payment_frequency, status FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $member = $stmt->fetch();

    if (!$member || $member['status'] !== 'Active' || !$member['membership_id']) {
        throw new RuntimeException('Acceptance email is only available for an active member.');
    }

    $config = kitLoadConfig();
    $message = hwfcMembershipAcceptanceMessage($member, $config);
} catch (Throwable $e) {
    error_log('Admin membership acceptance preview failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

$name = trim((string) (($member['preferred_name'] ?: $member['first_name']) . ' ' . $member['last_name']));
$mailto = 'mailto:' . rawurlencode($message['to']) . '?subject=' . rawurlencode($message['subject']) . '&body=' . rawurlencode($message['body']);
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
<header class="adminHeader"><div class="adminShell adminHeaderInner"><p class="adminBrand"><strong>HWFC Administration</strong></p><p class="adminMode">Version 0.6a3</p></div></header>
<main class="adminMain"><div class="adminShell">
    <p><a class="textLink" href="/admin/member.php?id=<?= acceptanceEsc($uuid) ?>">← Back To Member</a></p>
    <p class="eyebrow">Membership Onboarding</p>
    <h1>Acceptance Email</h1>

    <section class="adminDetailPanel">
        <h2><?= acceptanceEsc($name) ?></h2>
        <dl class="adminDetailGrid">
            <div><dt>Membership ID</dt><dd><strong><?= acceptanceEsc($member['membership_id']) ?></strong></dd></div>
            <div><dt>Email</dt><dd><?= acceptanceEsc($member['email']) ?></dd></div>
            <div><dt>Membership Start</dt><dd><?= acceptanceEsc($member['membership_start_date']) ?></dd></div>
            <div><dt>Membership Expiry</dt><dd><?= acceptanceEsc($member['membership_expiry_date']) ?></dd></div>
            <div><dt>Payment Plan</dt><dd><?= acceptanceEsc($member['payment_frequency']) ?></dd></div>
        </dl>

        <h3>Email Preview</h3>
        <p><strong><?= acceptanceEsc($message['subject']) ?></strong></p>
        <pre style="white-space:pre-wrap"><?= acceptanceEsc($message['body']) ?></pre>
        <p><a class="button buttonPrimary" href="<?= acceptanceEsc($mailto) ?>">Open In Email Client</a></p>
    </section>
</div></main>
</body>
</html>
