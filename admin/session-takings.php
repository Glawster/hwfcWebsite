<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';

hwfcRequireAdminDevelopmentAccess();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}

function takingsEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function takingsMoney(int $pence): string
{
    return '£' . number_format($pence / 100, 2);
}

$sessions = [
    'Tuesday 10:30 – All Abilities',
    'Wednesday 11:00 – Gentle / Social',
    'Thursday 20:00 – Competitive',
    "Friday 20:00 – Ladies' Coaching & Development",
    'Sunday 11:00 – Competitive',
    "Sunday 14:00 – Ladies' Match Session",
];

$flash = $_SESSION['admin_flash'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_flash'], $_SESSION['admin_error']);

try {
    $db = hwfcMemberDb();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
            throw new RuntimeException('Invalid admin form session.');
        }

        $sessionDate = trim((string) ($_POST['session_date'] ?? ''));
        $sessionName = trim((string) ($_POST['session_name'] ?? ''));
        $memberCount = filter_var($_POST['member_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        $visitorCount = filter_var($_POST['visitor_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        $collectedBy = trim((string) ($_POST['collected_by'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $sessionDate);
        if ($date === false || $date->format('Y-m-d') !== $sessionDate) {
            throw new RuntimeException('Enter a valid session date.');
        }
        if (!in_array($sessionName, $sessions, true)) {
            throw new RuntimeException('Choose a valid club session.');
        }
        if ($memberCount === false || $visitorCount === false) {
            throw new RuntimeException('Attendance counts must be whole numbers of zero or more.');
        }
        if ($memberCount + $visitorCount < 1) {
            throw new RuntimeException('Enter at least one attendee.');
        }
        if (strlen($collectedBy) > 120 || strlen($notes) > 1000) {
            throw new RuntimeException('Collector or notes are too long.');
        }

        $memberRate = 200;
        $visitorRate = 300;
        $totalPence = ($memberCount * $memberRate) + ($visitorCount * $visitorRate);

        $stmt = $db->prepare(<<<'SQL'
INSERT INTO session_takings (
    session_date, session_name, member_count, visitor_count,
    member_rate_pence, visitor_rate_pence, total_pence,
    collected_by, notes, recorded_at
) VALUES (
    :session_date, :session_name, :member_count, :visitor_count,
    :member_rate_pence, :visitor_rate_pence, :total_pence,
    :collected_by, :notes, :recorded_at
)
SQL);
        $stmt->execute([
            ':session_date' => $sessionDate,
            ':session_name' => $sessionName,
            ':member_count' => $memberCount,
            ':visitor_count' => $visitorCount,
            ':member_rate_pence' => $memberRate,
            ':visitor_rate_pence' => $visitorRate,
            ':total_pence' => $totalPence,
            ':collected_by' => $collectedBy !== '' ? $collectedBy : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':recorded_at' => gmdate('c'),
        ]);

        $_SESSION['admin_flash'] = 'Session takings recorded: ' . takingsMoney($totalPence) . '.';
        header('Location: /admin/session-takings.php');
        exit;
    }

    $records = $db->query('SELECT * FROM session_takings ORDER BY session_date DESC, id DESC LIMIT 100')->fetchAll();
    $summary = $db->query('SELECT COALESCE(SUM(total_pence), 0) AS total_pence, COALESCE(SUM(member_count), 0) AS members, COALESCE(SUM(visitor_count), 0) AS visitors FROM session_takings')->fetch();
} catch (Throwable $e) {
    error_log('Admin session takings failed: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['admin_error'] = $e->getMessage();
        header('Location: /admin/session-takings.php');
        exit;
    }
    $records = [];
    $summary = ['total_pence' => 0, 'members' => 0, 'visitors' => 0];
    $error = $e->getMessage();
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('Y-m-d');
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Takings | HWFC Administration</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="adminHeader"><div class="adminShell adminHeaderInner"><p class="adminBrand"><strong>HWFC Administration</strong></p><p class="adminMode">Local Development · Version 0.6a3</p></div></header>
<main class="adminMain"><div class="adminShell">
    <p><a class="textLink" href="/admin/">← Back To Administration</a></p>
    <p class="eyebrow">Club Income</p>
    <h1>Session Takings</h1>
    <p class="lead">Record the total attendance and cash/bank income for each session without tracking individual player attendance.</p>

    <?php if ($flash): ?><div class="adminSuccess" role="status"><?= takingsEsc($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="adminError" role="alert"><?= takingsEsc($error) ?></div><?php endif; ?>

    <div class="adminSummaryGrid" aria-label="Session income summary">
        <div class="adminSummaryCard"><span>Total Session Income</span><strong><?= takingsEsc(takingsMoney((int) $summary['total_pence'])) ?></strong></div>
        <div class="adminSummaryCard"><span>Member Attendances</span><strong><?= (int) $summary['members'] ?></strong></div>
        <div class="adminSummaryCard"><span>Visitor Attendances</span><strong><?= (int) $summary['visitors'] ?></strong></div>
    </div>

    <section class="adminDetailPanel">
        <h2>Record Session</h2>
        <form method="post" action="/admin/session-takings.php" class="adminMembershipForm">
            <input type="hidden" name="csrf" value="<?= takingsEsc($_SESSION['admin_csrf']) ?>">
            <div class="adminFormGrid">
                <label>Session Date<input type="date" name="session_date" value="<?= takingsEsc($today) ?>" required></label>
                <label>Session<select name="session_name" required><option value="">Choose…</option><?php foreach ($sessions as $session): ?><option value="<?= takingsEsc($session) ?>"><?= takingsEsc($session) ?></option><?php endforeach; ?></select></label>
                <label>Members (£2 each)<input type="number" name="member_count" min="0" max="100" value="0" required></label>
                <label>Visitors (£3 each)<input type="number" name="visitor_count" min="0" max="100" value="0" required></label>
                <label>Collected By<input type="text" name="collected_by" maxlength="120"></label>
                <label class="adminDetailWide">Notes<input type="text" name="notes" maxlength="1000"></label>
            </div>
            <p class="adminHelp">The total is calculated automatically from £2 per member and £3 per visitor.</p>
            <button class="button buttonPrimary" type="submit">Record Session Takings</button>
        </form>
    </section>

    <section class="adminPanel">
        <div class="adminPanelHeader"><h2>Recent Session Takings</h2><p>Latest 100 records.</p></div>
        <?php if (!$records): ?><div class="adminEmpty">No session takings have been recorded yet.</div><?php else: ?>
        <div class="adminTableWrap"><table class="adminTable"><thead><tr><th>Date</th><th>Session</th><th>Members</th><th>Visitors</th><th>Total</th><th>Collected By</th></tr></thead><tbody>
        <?php foreach ($records as $record): ?><tr>
            <td><?= takingsEsc($record['session_date']) ?></td>
            <td><?= takingsEsc($record['session_name']) ?></td>
            <td><?= (int) $record['member_count'] ?></td>
            <td><?= (int) $record['visitor_count'] ?></td>
            <td><strong><?= takingsEsc(takingsMoney((int) $record['total_pence'])) ?></strong></td>
            <td><?= takingsEsc($record['collected_by'] ?: '—') ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</div></main>
</body>
</html>
