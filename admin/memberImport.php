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

function importDefaultExportName(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('Ymd') . ' HWFC Member Export';
}

/** Keeps an administrator-chosen export name safe for a download header; always ends in .csv. */
function importExportFileName(string $requested): string
{
    $name = preg_replace('/\.csv$/i', '', trim($requested)) ?? '';
    $name = trim(preg_replace('/[^\p{L}\p{N} ._()\-–]+/u', '', $name) ?? '', ' .');
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
    return ($name !== '' ? $name : importDefaultExportName()) . '.csv';
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

            $defaultStartDate = trim((string) ($_POST['defaultStartDate'] ?? ''));
            if ($defaultStartDate !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $defaultStartDate);
                if ($date === false || $date->format('Y-m-d') !== $defaultStartDate) {
                    throw new RuntimeException('Enter a valid default membership start date.');
                }
            }

            $updateMode = ($_POST['updateExisting'] ?? '') === '1';
            $candidates = hwfcMemberImportCandidates(hwfcMemberImportReadFile((string) $upload['tmp_name']), $defaultStartDate !== '' ? $defaultStartDate : null);
            $_SESSION['memberImport'] = [
                'fileName' => $fileName,
                'updateMode' => $updateMode,
                'candidates' => hwfcMemberImportMarkDuplicates($db, $candidates, $updateMode),
            ];
            header('Location: /admin/memberImport.php');
            exit;
        }

        if ($action === 'export') {
            $csv = hwfcMemberExportCsv(hwfcMemberExportRows($db));
            $exportName = importExportFileName((string) ($_POST['exportFileName'] ?? ''));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $exportName . '"; filename*=UTF-8\'\'' . rawurlencode($exportName));
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

        $selectedRows = array_map('intval', array_filter((array) ($_POST['rows'] ?? []), 'is_string'));
        $selected = array_values(array_filter(
            $preview['candidates'],
            static fn(array $candidate): bool => in_array($candidate['result'], ['New', 'Update'], true) && in_array($candidate['rowNumber'], $selectedRows, true),
        ));
        if (!$selected) {
            throw new RuntimeException('Select at least one row to import or update.');
        }

        [$imported, $updated, $skipped] = hwfcMemberImportCommit($db, $selected, (string) $preview['fileName'], 'local-development-admin', (bool) ($preview['updateMode'] ?? false));
        unset($_SESSION['memberImport']);
        $_SESSION['admin_flash'] = $imported . ' new member ' . ($imported === 1 ? 'record' : 'records') . ' imported as Pending. '
            . $updated . ' existing member ' . ($updated === 1 ? 'record' : 'records') . ' updated.'
            . ($skipped > 0 ? ' ' . $skipped . ($skipped === 1 ? ' row was' : ' rows were') . ' skipped because the register changed after the preview; upload the spreadsheet again to review them.' : '');
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
$counts = array_merge(['New' => 0, 'Update' => 0, 'Unchanged' => 0, 'Duplicate' => 0, 'Invalid' => 0], array_count_values(array_column($candidates, 'result')));
$actionable = $counts['New'] + $counts['Update'];
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Import/Export | HWFC Administration</title>
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
    <h1>Member Import/Export</h1>
    <p class="lead">Import members from the club's member responses Google Form, update existing members, or export the register as CSV. Review the preview before anything is saved.</p>

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
            <label>Default Membership Start Date <span class="adminHelp">(optional)</span>
                <input type="date" name="defaultStartDate">
            </label>
            <p class="adminHelp">Used for rows without a “Membership start date” column value. Expiry is set to one year less a day after the start date.</p>
            <label class="adminCheckboxLabel"><input type="checkbox" name="updateExisting" value="1"> Update existing members matched by Membership ID</label>
            <p class="adminHelp">Use with an edited Export CSV. Changed values are shown for review before anything is saved. Blank cells leave values unchanged.</p>
            <p class="adminHelp">Use “Hillsborough Walking Football Club – Member Responses” downloaded from Google Sheets as Microsoft Excel (.xlsx) or as CSV for the Form Responses sheet. The column headings must match the form.</p>
            <button class="button buttonPrimary" type="submit">Preview Import</button>
        </form>
    </section>

    <section class="adminDetailPanel">
        <h2>How The Import Works</h2>
        <ul>
            <li>New members are added as <strong>Pending</strong>. Accept each one from their member record to allocate a Membership ID.</li>
            <li>Rows matching an existing member by name and date of birth or email address, or by Membership ID, are skipped. Existing records are not changed unless you tick update mode.</li>
            <li>In update mode, rows with a Membership ID update that member. Status, Membership ID, Timestamp, Application reference and Next payment due date are not changed by import. Payment details can only be changed for active members.</li>
            <li>The preview lets you choose which rows to apply.</li>
            <li>Rows with missing or invalid required details are skipped. Correct them in the spreadsheet and import again.</li>
            <li>Add a “Membership start date” column to the sheet to give members individual start dates, or set a default above.</li>
            <li>Names written like “OHara” are imported as “O'Hara”, and email addresses are saved in lowercase.</li>
            <li>A missing date of birth is imported as 01/01/1970 and noted on the member's record so it can be amended.</li>
            <li>Health, allergy, medication and support answers are combined into the member's health information.</li>
            <li>Membership type, club news, WhatsApp, photo, volunteering and confirmation answers are kept together as the member's Form Answers, shown on Member Details.</li>
            <li>Photo consent is recorded only for a plain “Yes”; “Please ask me first” is recorded as no consent and kept in Form Answers.</li>
        </ul>
    </section>
    <?php else: ?>
    <div class="adminSummaryGrid" aria-label="Import preview summary">
        <div class="adminSummaryCard"><span>Spreadsheet Rows</span><strong><?= count($candidates) ?></strong></div>
        <div class="adminSummaryCard"><span>New Members</span><strong><?= (int) $counts['New'] ?></strong></div>
        <?php if (!empty($preview['updateMode'])): ?>
        <div class="adminSummaryCard"><span>Updates</span><strong><?= (int) $counts['Update'] ?></strong></div>
        <div class="adminSummaryCard"><span>Unchanged</span><strong><?= (int) $counts['Unchanged'] ?></strong></div>
        <?php endif; ?>
        <div class="adminSummaryCard"><span>Duplicates</span><strong><?= (int) $counts['Duplicate'] ?></strong></div>
        <div class="adminSummaryCard"><span>Invalid</span><strong><?= (int) $counts['Invalid'] ?></strong></div>
    </div>

    <section class="adminActionPanel" aria-labelledby="importActionsHeading">
        <h2 id="importActionsHeading">Confirm Import</h2>
        <p>Previewing <?= importEsc((string) $preview['fileName']) ?><?= !empty($preview['updateMode']) ? ' with updates to existing members' : '' ?>. Only the ticked New and Update rows are saved. Each is checked against the register again when you confirm.</p>
        <div class="adminActionRow">
            <?php if ($actionable > 0): ?>
            <form method="post" action="/admin/memberImport.php" id="importCommitForm">
                <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="commit">
                <button class="button buttonPrimary" type="submit">Apply <span data-selected-count><?= $actionable ?></span> Selected <?= $actionable === 1 ? 'Row' : 'Rows' ?></button>
            </form>
            <button class="button buttonSecondary" type="button" data-select-rows="all">Check All</button>
            <button class="button buttonSecondary" type="button" data-select-rows="none">Uncheck All</button>
            <?php endif; ?>
            <form method="post" action="/admin/memberImport.php">
                <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="button buttonSecondary" type="submit">Cancel Import</button>
            </form>
        </div>
    </section>

    <section class="adminPanel">
        <div class="adminPanelHeader"><h2>Import Preview</h2><p>Health details are not shown here; they are visible on each member record.</p></div>
        <div class="adminTableWrap"><table class="adminTable"><thead><tr><th>Apply</th><th>Row</th><th>Name</th><th>Email</th><th>Date Of Birth</th><th>Start Date</th><th>Membership Type</th><th>Health Info</th><th>Result</th></tr></thead><tbody>
        <?php foreach ($candidates as $candidate): ?>
            <tr>
                <td>
                    <?php if (in_array($candidate['result'], ['New', 'Update'], true)): ?>
                    <input type="checkbox" name="rows[]" value="<?= (int) $candidate['rowNumber'] ?>" form="importCommitForm" checked aria-label="Apply spreadsheet row <?= (int) $candidate['rowNumber'] ?>" data-import-row>
                    <?php endif; ?>
                </td>
                <td><?= (int) $candidate['rowNumber'] ?></td>
                <td><?= importEsc($candidate['summary']['name']) ?><?php if (!empty($candidate['summary']['membershipId'])): ?><br><?= importEsc($candidate['summary']['membershipId']) ?><?php endif; ?></td>
                <td><?= importEsc($candidate['summary']['email']) ?></td>
                <td><?= importEsc(importDisplayDate((string) $candidate['summary']['dateOfBirth'])) ?></td>
                <td><?= importEsc($candidate['summary']['membershipStartDate'] ? importDisplayDate((string) $candidate['summary']['membershipStartDate']) : '—') ?></td>
                <td><?= importEsc($candidate['summary']['membershipType'] ?: '—') ?></td>
                <td><?= $candidate['summary']['healthInformation'] ? 'Yes' : 'No' ?></td>
                <td>
                    <span class="adminStatus"><?= importEsc($candidate['result']) ?></span>
                    <?php foreach ($candidate['changes'] ?? [] as $change): ?><br><strong><?= importEsc($change['field']) ?>:</strong> <?= importEsc($change['from'] !== '' ? $change['from'] : '—') ?> → <?= importEsc($change['to']) ?><?php endforeach; ?>
                    <?php foreach ($candidate['messages'] as $message): ?><br><?= importEsc($message) ?><?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <script>
    (() => {
        const boxes = Array.from(document.querySelectorAll('[data-import-row]'));
        const count = document.querySelector('[data-selected-count]');
        const refresh = () => { if (count) count.textContent = boxes.filter((box) => box.checked).length; };
        document.querySelectorAll('[data-select-rows]').forEach((button) => {
            button.addEventListener('click', () => {
                boxes.forEach((box) => { box.checked = button.dataset.selectRows === 'all'; });
                refresh();
            });
        });
        boxes.forEach((box) => box.addEventListener('change', refresh));
    })();
    </script>
    <?php endif; ?>

    <section class="adminDetailPanel">
        <h2>Export Member Responses</h2>
        <form method="post" action="/admin/memberImport.php" class="adminMembershipForm">
            <input type="hidden" name="csrf" value="<?= importEsc($_SESSION['admin_csrf']) ?>">
            <input type="hidden" name="action" value="export">
            <label>File Name
                <input type="text" name="exportFileName" value="<?= importEsc(importDefaultExportName()) ?>" maxlength="120">
            </label>
            <p class="adminHelp">“.csv” is added automatically. Letters, numbers, spaces, dots, hyphens, underscores and brackets are kept.</p>
            <p class="adminHelp">Downloads every Pending, Active and Lapsed member as a CSV file with the same columns as the member responses Google Form, followed by Membership start date, Membership ID, status, membership and payment dates, payment plan and status, second emergency contact relationship and application reference. It can be worked on offline and imported again. Rejected applications are not included. Members who applied on the website have no form answers, so those columns are blank for them.</p>
            <p class="adminHelp">The file contains health and emergency-contact details. Keep it on a club device only and delete it when you have finished. To apply offline changes, import it with “Update existing members matched by Membership ID” ticked.</p>
            <button class="button buttonPrimary" type="submit">Export CSV</button>
        </form>
    </section>
</div></main>
</body>
</html>
