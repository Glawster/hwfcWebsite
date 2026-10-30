<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberImport.php';

hwfcRequireAdminDevelopmentAccess();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}

function importEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function importDisplayDate(string $isoDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
    return $date === false ? $isoDate : $date->format('d M Y');
}

$flash = $_SESSION['admin_flash'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_flash'], $_SESSION['admin_error']);
$preview = $_SESSION['memberImport'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
            throw new RuntimeException('Invalid admin form session.');
        }

        $action = trim((string) ($_POST['action'] ?? ''));
        $db = hwfcMemberDb();

        if ($action === 'preview') {
            unset($_SESSION['memberImport']);
            $upload = $_FILES['memberSpreadsheet'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose the member responses spreadsheet (.xlsx or .csv) to import.');
            }
            $fileName = basename((string) ($upload['name'] ?? 'spreadsheet'));
            if (!preg_match('/\.(xlsx|csv)$/i', $fileName)) {
                throw new RuntimeException('Upload the spreadsheet as an Excel (.xlsx) or CSV (.csv) file.');
            }

            $candidates = hwfcMemberImportCandidates(hwfcMemberImportReadFile((string) $upload['tmp_name']));
            $_SESSION['memberImport'] = [
                'fileName' => $fileName,
                'candidates' => hwfcMemberImportMarkDuplicates($db, $candidates),
            ];
            header('Location: /admin/memberImport.php');
            exit;
        }

        if ($action === 'export') {
            $csv = hwfcMemberExportCsv(hwfcMemberExportRows($db));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="HWFC Member Responses export ' . gmdate('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            echo $csv;
            exit;
        }

        if ($action === 'cancel') {
            unset($_SESSION['memberImport']);
            $_SESSION['admin_flash'] = 'Member import cancelled. No member records were changed.';
            header('Location: /admin/memberImport.php');
            exit;
        }

        if ($action !== 'commit') {
            throw new RuntimeException('Unknown member-import action.');
        }
        if (!is_array($preview) || !isset($preview['candidates'])) {
            throw new RuntimeException('The import preview is no longer available. Upload the spreadsheet again.');
        }

        [$imported, $skipped] = hwfcMemberImportCommit($db, $preview['candidates'], (string) $preview['fileName'], 'local-development-admin');
        unset($_SESSION['memberImport']);
        $_SESSION['admin_flash'] = $imported . ' member ' . ($imported === 1 ? 'record' : 'records') . ' imported as Pending. '
            . $skipped . ' duplicate or invalid ' . ($skipped === 1 ? 'row was' : 'rows were') . ' skipped.';
        header('Location: /admin/memberImport.php');
        exit;
    } catch (Throwable $e) {
        error_log('Admin member import failed: ' . $e->getMessage());
        $_SESSION['admin_error'] = $e->getMessage();
        header('Location: /admin/memberImport.php');
        exit;
    }
}

$candidates = is_array($preview) ? ($preview['candidates'] ?? []) : [];
$counts = array_merge(['New' => 0, 'Duplicate' => 0, 'Invalid' => 0], array_count_values(array_column($candidates, 'result')));
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Import | HWFC Administration</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="adminHeader"><div class="adminShell adminHeaderInner"><p class="adminBrand"><strong>HWFC Administration</strong></p><p class="adminMode">Local Development · Version 0.6a5</p></div></header>
<main class="adminMain"><div class="adminShell">
    <p><a class="textLink" href="/admin/">← Back To Administration</a></p>
    <p class="eyebrow">Members</p>
    <h1>Member Import</h1>
    <p class="lead">Import existing members from the club's member responses Google Form. Review the preview before anything is saved.</p>

    <?php if ($flash): ?><div class="adminSuccess" role="status"><?= importEsc($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="adminError" role="alert"><?= importEsc($error) ?></div><?php endif; ?>

    <?php if (!$candidates): ?>
    <section class="adminDetailPanel">
        <h2>Upload Member Responses</h2>
        <form method="post" action="/admin/memberImport.php" enctype="multipart/form-data" class="adminMembershipForm">
            <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="preview">
            <label>Member Responses Spreadsheet
                <input type="file" name="memberSpreadsheet" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </label>
            <p class="adminHelp">Use “Hillsborough Walking Football Club – Member Responses” downloaded from Google Sheets as Microsoft Excel (.xlsx) or as CSV for the Form Responses sheet. The column headings must match the form.</p>
            <button class="button buttonPrimary" type="submit">Preview Import</button>
        </form>
    </section>

    <section class="adminDetailPanel">
        <h2>How The Import Works</h2>
        <ul>
            <li>New members are added as <strong>Pending</strong>. Accept each one from their member record to allocate a Membership ID.</li>
            <li>Rows matching an existing member by name and date of birth or email address are skipped. Existing records are never changed.</li>
            <li>Rows with missing or invalid required details are skipped. Correct them in the spreadsheet and import again.</li>
            <li>Health, allergy, medication and support answers are combined into the member's health information.</li>
            <li>Membership type, club news, WhatsApp and volunteering answers are added to the member's Notes.</li>
            <li>Photo consent is recorded only for a plain “Yes”; “Please ask me first” is recorded as no consent.</li>
        </ul>
    </section>
    <?php else: ?>
    <div class="adminSummaryGrid" aria-label="Import preview summary">
        <div class="adminSummaryCard"><span>Spreadsheet Rows</span><strong><?= count($candidates) ?></strong></div>
        <div class="adminSummaryCard"><span>Ready To Import</span><strong><?= (int) $counts['New'] ?></strong></div>
        <div class="adminSummaryCard"><span>Duplicates</span><strong><?= (int) $counts['Duplicate'] ?></strong></div>
        <div class="adminSummaryCard"><span>Invalid</span><strong><?= (int) $counts['Invalid'] ?></strong></div>
    </div>

    <section class="adminActionPanel" aria-labelledby="importActionsHeading">
        <h2 id="importActionsHeading">Confirm Import</h2>
        <p>Previewing <?= importEsc((string) $preview['fileName']) ?>. Only rows marked New will be saved; duplicates are checked again when you confirm.</p>
        <div class="adminActionRow">
            <?php if ($counts['New'] > 0): ?>
            <form method="post" action="/admin/memberImport.php">
                <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="commit">
                <button class="button buttonPrimary" type="submit">Import <?= (int) $counts['New'] ?> New <?= $counts['New'] === 1 ? 'Member' : 'Members' ?></button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/memberImport.php">
                <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="button buttonSecondary" type="submit">Cancel Import</button>
            </form>
        </div>
    </section>

    <section class="adminPanel">
        <div class="adminPanelHeader"><h2>Import Preview</h2><p>Health details are not shown here; they are visible on each member record after import.</p></div>
        <div class="adminTableWrap"><table class="adminTable"><thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Date Of Birth</th><th>Membership Type</th><th>Health Info</th><th>Result</th></tr></thead><tbody>
        <?php foreach ($candidates as $candidate): ?>
            <tr>
                <td><?= (int) $candidate['rowNumber'] ?></td>
                <td><?= importEsc($candidate['summary']['name']) ?></td>
                <td><?= importEsc($candidate['summary']['email']) ?></td>
                <td><?= importEsc(importDisplayDate((string) $candidate['summary']['dateOfBirth'])) ?></td>
                <td><?= importEsc($candidate['summary']['membershipType'] ?: '—') ?></td>
                <td><?= $candidate['summary']['healthInformation'] ? 'Yes' : 'No' ?></td>
                <td>
                    <span class="adminStatus"><?= importEsc($candidate['result']) ?></span>
                    <?php foreach ($candidate['messages'] as $message): ?><br><?= importEsc($message) ?><?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>

    <section class="adminDetailPanel">
        <h2>Export Member Responses</h2>
        <form method="post" action="/admin/memberImport.php" class="adminMembershipForm">
            <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="export">
            <p class="adminHelp">Downloads every Pending, Active and Lapsed member as a CSV file with the same columns as the member responses Google Form, so it can be worked on offline or imported again. Rejected applications are not included. The confirmation columns are left blank because the website does not record those answers separately.</p>
            <p class="adminHelp">The file contains health and emergency-contact details. Keep it on a club device only and delete it when you have finished. Importing it again adds only members that are not already in the register; it does not update existing members.</p>
            <button class="button buttonSecondary" type="submit">Export CSV</button>
        </form>
    </section>
</div></main>
</body>
</html>
