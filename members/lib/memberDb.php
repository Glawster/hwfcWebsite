<?php

declare(strict_types=1);

require_once __DIR__ . '/memberConfig.php';

function hwfcMemberDb(): PDO
{
    $path = hwfcMemberDatabasePath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create membership data directory.');
    }

    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = WAL');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_uuid TEXT NOT NULL UNIQUE,
    membership_id TEXT UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    preferred_name TEXT,
    date_of_birth TEXT NOT NULL,
    address TEXT NOT NULL,
    postcode TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    preferred_contact TEXT NOT NULL,
    emergency_name TEXT NOT NULL,
    emergency_relationship TEXT NOT NULL,
    emergency_phone TEXT NOT NULL,
    second_emergency_name TEXT,
    second_emergency_relationship TEXT,
    second_emergency_phone TEXT,
    health_relevant INTEGER NOT NULL DEFAULT 0,
    health_details TEXT,
    photo_consent INTEGER NOT NULL DEFAULT 0,
    privacy_version TEXT NOT NULL,
    admin_notes TEXT,
    membership_start_date TEXT,
    membership_expiry_date TEXT,
    payment_frequency TEXT NOT NULL DEFAULT 'Not Set',
    payment_status TEXT NOT NULL DEFAULT 'Not Recorded',
    last_payment_date TEXT,
    paid_through_date TEXT,
    next_payment_due_date TEXT,
    submitted_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pending'
)
SQL);

    $columns = $db->query('PRAGMA table_info(membership_applications)')->fetchAll();
    $columnNames = array_column($columns, 'name');
    if (!in_array('second_emergency_relationship', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN second_emergency_relationship TEXT');
    }
    if (!in_array('membership_id', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN membership_id TEXT');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS membership_applications_membership_id_uq ON membership_applications (membership_id) WHERE membership_id IS NOT NULL');
    }
    if (!in_array('admin_notes', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN admin_notes TEXT');
    }
    if (!in_array('membership_start_date', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN membership_start_date TEXT');
    }
    if (!in_array('membership_expiry_date', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN membership_expiry_date TEXT');
    }
    if (!in_array('payment_frequency', $columnNames, true)) {
        $db->exec("ALTER TABLE membership_applications ADD COLUMN payment_frequency TEXT NOT NULL DEFAULT 'Not Set'");
    }
    if (!in_array('payment_status', $columnNames, true)) {
        $db->exec("ALTER TABLE membership_applications ADD COLUMN payment_status TEXT NOT NULL DEFAULT 'Not Recorded'");
    }
    if (!in_array('last_payment_date', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN last_payment_date TEXT');
    }
    if (!in_array('paid_through_date', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN paid_through_date TEXT');
    }
    if (!in_array('next_payment_due_date', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN next_payment_due_date TEXT');
    }
    if (!in_array('form_answers', $columnNames, true)) {
        $db->exec('ALTER TABLE membership_applications ADD COLUMN form_answers TEXT');
        hwfcMoveFormAnswersFromNotes($db);
    }

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_id_sequence (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    last_value INTEGER NOT NULL DEFAULT 0
)
SQL);
    $db->exec('INSERT OR IGNORE INTO membership_id_sequence (id, last_value) VALUES (1, 0)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_uuid TEXT NOT NULL,
    event_type TEXT NOT NULL,
    event_at TEXT NOT NULL,
    details TEXT
)
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS membership_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_uuid TEXT NOT NULL,
    membership_id TEXT NOT NULL,
    payment_date TEXT NOT NULL,
    amount_pence INTEGER NOT NULL,
    payment_type TEXT NOT NULL,
    paid_through_date TEXT,
    recorded_at TEXT NOT NULL,
    FOREIGN KEY (application_uuid) REFERENCES membership_applications(application_uuid)
)
SQL);
    $db->exec('CREATE INDEX IF NOT EXISTS membership_payments_application_idx ON membership_payments (application_uuid, payment_date DESC, id DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS session_takings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_date TEXT NOT NULL,
    session_name TEXT NOT NULL,
    member_count INTEGER NOT NULL DEFAULT 0,
    visitor_count INTEGER NOT NULL DEFAULT 0,
    member_rate_pence INTEGER NOT NULL DEFAULT 200,
    visitor_rate_pence INTEGER NOT NULL DEFAULT 300,
    total_pence INTEGER NOT NULL,
    collected_by TEXT,
    notes TEXT,
    recorded_at TEXT NOT NULL,
    attendance_source TEXT NOT NULL DEFAULT 'manual',
    venue TEXT,
    expected_total_pence INTEGER,
    actual_total_pence INTEGER
)
SQL);

    $takingsColumns = $db->query('PRAGMA table_info(session_takings)')->fetchAll();
    $takingsColumnNames = array_column($takingsColumns, 'name');
    if (!in_array('attendance_source', $takingsColumnNames, true)) {
        $db->exec("ALTER TABLE session_takings ADD COLUMN attendance_source TEXT NOT NULL DEFAULT 'manual'");
    }
    if (!in_array('venue', $takingsColumnNames, true)) {
        $db->exec('ALTER TABLE session_takings ADD COLUMN venue TEXT');
    }
    if (!in_array('expected_total_pence', $takingsColumnNames, true)) {
        $db->exec('ALTER TABLE session_takings ADD COLUMN expected_total_pence INTEGER');
    }
    if (!in_array('actual_total_pence', $takingsColumnNames, true)) {
        $db->exec('ALTER TABLE session_takings ADD COLUMN actual_total_pence INTEGER');
    }
    $db->exec('CREATE INDEX IF NOT EXISTS session_takings_date_idx ON session_takings (session_date DESC, id DESC)');

    @chmod($path, 0600);
    return $db;
}

function hwfcNextMembershipId(PDO $db): string
{
    $used = $db->query("SELECT membership_id FROM membership_applications WHERE membership_id IS NOT NULL AND membership_id LIKE 'HWFC-%' ORDER BY membership_id")->fetchAll(PDO::FETCH_COLUMN);

    $next = 1;
    foreach ($used as $membershipId) {
        if (!preg_match('/^HWFC-(\d{6})$/', (string) $membershipId, $matches)) {
            continue;
        }

        $value = (int) $matches[1];
        if ($value === $next) {
            $next++;
            continue;
        }

        if ($value > $next) {
            break;
        }
    }

    return sprintf('HWFC-%06d', $next);
}

function hwfcAudit(PDO $db, string $uuid, string $event, array $details = []): void
{
    $stmt = $db->prepare('INSERT INTO membership_audit (application_uuid, event_type, event_at, details) VALUES (:uuid, :event, :at, :details)');
    $stmt->execute([
        ':uuid' => $uuid,
        ':event' => $event,
        ':at' => gmdate('c'),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
    ]);
}

/**
 * Member responses form answers kept as labelled lines in form_answers, one per line, so the
 * record reads as a single block while import/export can still address each answer.
 */
const HWFC_MEMBER_FORM_ANSWER_LABELS = [
    'membershipType' => 'Membership type',
    'clubNews' => 'Club news and updates',
    'whatsApp' => 'Club WhatsApp group',
    'photoConsent' => 'Photographs or videos',
    'helping' => 'Helping with club activities',
    'accuracyConfirmed' => 'Information accurate',
    'changesConfirmed' => 'Will report changes',
    'riskConfirmed' => 'Physical activity understood',
    'privacyAcknowledged' => 'Privacy notice acknowledged',
];

function hwfcFormAnswersParse(?string $text): array
{
    $answers = [];
    $keys = array_flip(HWFC_MEMBER_FORM_ANSWER_LABELS);
    foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
        $parts = explode(': ', $line, 2);
        if (count($parts) === 2 && isset($keys[$parts[0]])) {
            $answers[$keys[$parts[0]]] = trim($parts[1]);
        }
    }
    return $answers;
}

function hwfcFormAnswersBuild(array $answers): ?string
{
    $lines = [];
    foreach (HWFC_MEMBER_FORM_ANSWER_LABELS as $key => $label) {
        $value = trim(preg_replace('/\s*\R\s*/u', ' ', (string) ($answers[$key] ?? '')) ?? '');
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }
    return $lines ? implode("\n", $lines) : null;
}

/** One-off migration: earlier imports stored the form answers as lines in admin_notes. */
function hwfcMoveFormAnswersFromNotes(PDO $db): void
{
    $noteLabels = [
        'Membership type' => 'membershipType',
        'Club news and updates' => 'clubNews',
        'Club WhatsApp group' => 'whatsApp',
        'Photographs or videos answer' => 'photoConsent',
        'Helping with club activities' => 'helping',
    ];
    $rows = $db->query('SELECT application_uuid, admin_notes FROM membership_applications WHERE admin_notes IS NOT NULL')->fetchAll();
    $update = $db->prepare('UPDATE membership_applications SET admin_notes = :notes, form_answers = :answers WHERE application_uuid = :uuid');

    $db->beginTransaction();
    try {
        foreach ($rows as $row) {
            $answers = [];
            $kept = [];
            foreach (preg_split('/\R/u', (string) $row['admin_notes']) ?: [] as $line) {
                $parts = explode(': ', $line, 2);
                if (count($parts) === 2 && isset($noteLabels[$parts[0]])) {
                    $answers[$noteLabels[$parts[0]]] = $parts[1];
                } else {
                    $kept[] = $line;
                }
            }
            if (!$answers) {
                continue;
            }
            $notes = trim(implode("\n", $kept));
            $update->execute([
                ':notes' => $notes !== '' ? $notes : null,
                ':answers' => hwfcFormAnswersBuild($answers),
                ':uuid' => $row['application_uuid'],
            ]);
            hwfcAudit($db, $row['application_uuid'], 'form_answers_moved_from_notes', ['fields' => array_keys($answers), 'actor' => 'system']);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
