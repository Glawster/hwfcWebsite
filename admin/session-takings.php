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

function takingsDay(string $isoDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
    return $date === false ? $isoDate : $date->format('D');
}

function omfDateToIso(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!d/m/y', trim($value));
    if ($date === false) {
        throw new RuntimeException('OMF report contains an invalid session date: ' . $value);
    }
    return $date->format('Y-m-d');
}

function parsePoundsToPence(string $value): int
{
    $normalised = trim(str_replace(['£', ','], '', $value));
    if ($normalised === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalised)) {
        throw new RuntimeException('Enter the actual takings as a valid amount, for example 28.00.');
    }
    return (int) round(((float) $normalised) * 100);
}

function parseOmfAttendanceReport(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The OMF attendance report could not be opened.');
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map(static fn($value): string => trim((string) $value), $row);
    }
    fclose($handle);

    if (count($rows) < 6) {
        throw new RuntimeException('This does not look like an OMF attendance report.');
    }

    $expectedHeaders = ['Week', 'Date', 'Venue', 'Day', 'Name'];
    foreach ($expectedHeaders as $index => $heading) {
        if (($rows[$index][0] ?? '') !== $heading) {
            throw new RuntimeException('Unexpected OMF report format. Expected ' . $heading . ' row.');
        }
    }

    $totalRow = null;
    foreach ($rows as $row) {
        if (($row[0] ?? '') === 'Session Total') {
            $totalRow = $row;
            break;
        }
    }
    if ($totalRow === null) {
        throw new RuntimeException('OMF report does not contain a Session Total row.');
    }

    $sessions = [];
    $columnCount = max(count($rows[1]), count($rows[2]), count($rows[4]), count($totalRow));
    for ($column = 1; $column < $columnCount; $column++) {
        $dateText = $rows[1][$column] ?? '';
        $venue = $rows[2][$column] ?? '';
        $name = $rows[4][$column] ?? '';
        $totalText = $totalRow[$column] ?? '';

        if ($dateText === '' || $name === '' || $totalText === '') {
            continue;
        }
        if (!ctype_digit($totalText)) {
            throw new RuntimeException('OMF report contains a non-numeric Session Total.');
        }

        $sessions[] = [
            'session_date' => omfDateToIso($dateText),
            'session_name' => $name,
            'venue' => $venue,
            'member_count' => (int) $totalText,
        ];
    }

    if (!$sessions) {
        throw new RuntimeException('No scheduled OMF sessions with attendance totals were found.');
    }

    return $sessions;
}

$flash = $_SESSION['admin_flash'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_flash'], $_SESSION['admin_error']);
$importedSessions = $_SESSION['omf_session_import'] ?? [];

try {
    $db = hwfcMemberDb();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
            throw new RuntimeException('Invalid admin form session.');
        }

        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'import_omf') {
            $upload = $_FILES['omf_report'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose an OMF attendanceReport-YYYY-MM.csv file to import.');
            }
            if (($upload['size'] ?? 0) > 2 * 1024 * 1024) {
                throw new RuntimeException('The OMF report is unexpectedly large.');
            }
            $importedSessions = parseOmfAttendanceReport((string) $upload['tmp_name']);
            $_SESSION['omf_session_import'] = $importedSessions;
            $_SESSION['admin_flash'] = count($importedSessions) . ' OMF sessions loaded for reconciliation.';
            header('Location: /admin/session-takings.php');
            exit;
        }

        if ($action === 'clear_import') {
            unset($_SESSION['omf_session_import']);
            $_SESSION['admin_flash'] = 'OMF import cleared.';
            header('Location: /admin/session-takings.php');
            exit;
        }

        if ($action !== 'record_omf') {
            throw new RuntimeException('Unknown session-takings action.');
        }

        $index = filter_var($_POST['session_index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($index === false || !isset($importedSessions[$index]) || !is_array($importedSessions[$index])) {
            throw new RuntimeException('The imported OMF session is no longer available. Re-import the report.');
        }

        $session = $importedSessions[$index];
        $actualPence = parsePoundsToPence((string) ($_POST['actual_takings'] ?? ''));
        $collectedBy = trim((string) ($_POST['collected_by'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (strlen($collectedBy) > 120 || strlen($notes) > 1000) {
            throw new RuntimeException('Collector or notes are too long.');
        }

        $memberCount = (int) $session['member_count'];
        $memberRate = 200;
        $expectedPence = $memberCount * $memberRate;

        $duplicate = $db->prepare('SELECT id FROM session_takings WHERE session_date = :session_date AND session_name = :session_name LIMIT 1');
        $duplicate->execute([
            ':session_date' => $session['session_date'],
            ':session_name' => $session['session_name'],
        ]);
        if ($duplicate->fetch()) {
            throw new RuntimeException('Takings have already been recorded for this session.');
        }

        $stmt = $db->prepare(<<<'SQL'
INSERT INTO session_takings (
    session_date, session_name, member_count, visitor_count,
    member_rate_pence, visitor_rate_pence, total_pence,
    collected_by, notes, recorded_at, attendance_source,
    venue, expected_total_pence, actual_total_pence
) VALUES (
    :session_date, :session_name, :member_count, 0,
    :member_rate_pence, 0, :total_pence,
    :collected_by, :notes, :recorded_at, 'omf',
    :venue, :expected_total_pence, :actual_total_pence
)
SQL);
        $stmt->execute([
            ':session_date' => $session['session_date'],
            ':session_name' => $session['session_name'],
            ':member_count' => $memberCount,
            ':member_rate_pence' => $memberRate,
            ':total_pence' => $actualPence,
            ':collected_by' => $collectedBy !== '' ? $collectedBy : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':recorded_at' => gmdate('c'),
            ':venue' => $session['venue'] !== '' ? $session['venue'] : null,
            ':expected_total_pence' => $expectedPence,
            ':actual_total_pence' => $actualPence,
        ]);

        $_SESSION['admin_flash'] = 'Session reconciled. Expected ' . takingsMoney($expectedPence) . ', actual ' . takingsMoney($actualPence) . '.';
        header('Location: /admin/session-takings.php');
        exit;
    }

    $records = $db->query('SELECT * FROM session_takings ORDER BY session_date DESC, id DESC LIMIT 100')->fetchAll();
    $summary = $db->query('SELECT COALESCE(SUM(COALESCE(actual_total_pence, total_pence)), 0) AS total_pence, COALESCE(SUM(member_count), 0) AS members FROM session_takings')->fetch();
} catch (Throwable $e) {
    error_log('Admin session takings failed: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['admin_error'] = $e->getMessage();
        header('Location: /admin/session-takings.php');
        exit;
    }
    $records = [];
    $summary = ['total_pence' => 0, 'members' => 0];
    $error = $e->getMessage();
}
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
    <p class="lead">Import final member attendance totals from organiseMyFooty and reconcile the expected £2 session fees against the amount actually received.</p>

    <?php if ($flash): ?><div class="adminSuccess" role="status"><?= takingsEsc($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="adminError" role="alert"><?= takingsEsc($error) ?></div><?php endif; ?>

    <div class="adminSummaryGrid" aria-label="Session income summary">
        <div class="adminSummaryCard"><span>Total Session Income</span><strong><?= takingsEsc(takingsMoney((int) $summary['total_pence'])) ?></strong></div>
        <div class="adminSummaryCard"><span>Member Attendances</span><strong><?= (int) $summary['members'] ?></strong></div>
    </div>

    <section class="adminDetailPanel">
        <h2>Import organiseMyFooty Attendance</h2>
        <form method="post" action="/admin/session-takings.php" enctype="multipart/form-data" class="adminMembershipForm">
            <input type="hidden" name="csrf" value="<?= takingsEsc($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="import_omf">
            <label>OMF Monthly Attendance Report
                <input type="file" name="omf_report" accept=".csv,text/csv" required>
            </label>
            <p class="adminHelp">Use organiseMyFooty's <code>output/attendanceReport-YYYY-MM.csv</code>. The report's Session Total row is used as the final member attendance count.</p>
            <button class="button buttonPrimary" type="submit">Load OMF Report</button>
        </form>
        <?php if ($importedSessions): ?>
        <form method="post" action="/admin/session-takings.php" style="margin-top:1rem">
            <input type="hidden" name="csrf" value="<?= takingsEsc($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="clear_import">
            <button class="button buttonSecondary" type="submit">Clear Imported Report</button>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($importedSessions): ?>
    <section class="adminPanel">
        <div class="adminPanelHeader"><h2>Reconcile Imported Sessions</h2><p>Each attending member is £2. Enter the amount actually received so any difference remains visible.</p></div>
        <div class="adminTableWrap"><table class="adminTable"><thead><tr><th>Date</th><th>Day</th><th>Venue</th><th>Members</th><th>Actual £</th><th>Collected By</th><th>Notes</th><th></th></tr></thead><tbody>
        <?php foreach ($importedSessions as $index => $session): ?>
            <?php $memberExpected = ((int) $session['member_count']) * 200; ?>
            <tr>
                <td><?= takingsEsc($session['session_date']) ?></td>
                <td><strong><?= takingsEsc(takingsDay($session['session_date'])) ?></strong></td>
                <td><?= takingsEsc($session['venue'] ?: '—') ?></td>
                <td><?= (int) $session['member_count'] ?> (<?= takingsEsc(takingsMoney($memberExpected)) ?>)</td>
                <td colspan="4">
                    <form method="post" action="/admin/session-takings.php" class="adminMembershipForm">
                        <input type="hidden" name="csrf" value="<?= takingsEsc($_SESSION['admin_csrf']) ?>">
                        <input type="hidden" name="action" value="record_omf">
                        <input type="hidden" name="session_index" value="<?= (int) $index ?>">
                        <div class="adminActionRow adminReconcileRow">
                            <input aria-label="Actual takings" type="text" name="actual_takings" inputmode="decimal" value="<?= takingsEsc(number_format($memberExpected / 100, 2, '.', '')) ?>" required>
                            <input aria-label="Collected by" type="text" name="collected_by" maxlength="120">
                            <input aria-label="Notes" type="text" name="notes" maxlength="1000">
                            <button class="button buttonPrimary" type="submit">Record</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>

    <section class="adminPanel">
        <div class="adminPanelHeader"><h2>Recent Session Takings</h2><p>Latest 100 reconciled sessions.</p></div>
        <?php if (!$records): ?><div class="adminEmpty">No session takings have been recorded yet.</div><?php else: ?>
        <div class="adminTableWrap"><table class="adminTable"><thead><tr><th>Date</th><th>Day</th><th>Members</th><th>Expected</th><th>Actual</th><th>Difference</th><th>Source</th></tr></thead><tbody>
        <?php foreach ($records as $record): ?>
            <?php
                $expected = $record['expected_total_pence'] !== null ? (int) $record['expected_total_pence'] : ((int) $record['member_count'] * 200);
                $actual = $record['actual_total_pence'] !== null ? (int) $record['actual_total_pence'] : (int) $record['total_pence'];
                $difference = $actual - $expected;
            ?>
            <tr>
                <td><?= takingsEsc($record['session_date']) ?></td>
                <td><?= takingsEsc(takingsDay((string) $record['session_date'])) ?></td>
                <td><?= (int) $record['member_count'] ?></td>
                <td><?= takingsEsc(takingsMoney($expected)) ?></td>
                <td><strong><?= takingsEsc(takingsMoney($actual)) ?></strong></td>
                <td><?= ($difference > 0 ? '+' : '') . takingsEsc(takingsMoney($difference)) ?></td>
                <td><?= takingsEsc(strtoupper((string) ($record['attendance_source'] ?? 'manual'))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</div></main>
</body>
</html>