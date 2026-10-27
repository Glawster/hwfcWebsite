<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/memberDb.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function usage(): never
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php members/bin/memberData.php export --email=user@example.com\n");
    fwrite(STDERR, "  php members/bin/memberData.php correct --email=user@example.com --field=phone --value='07700 900000'\n");
    fwrite(STDERR, "  php members/bin/memberData.php delete --email=user@example.com --confirm\n");
    exit(2);
}

$command = $argv[1] ?? '';
$options = getopt('', ['email:', 'field:', 'value:', 'confirm']);
$email = strtolower(trim((string) ($options['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    usage();
}

$db = hwfcMemberDb();
$stmt = $db->prepare('SELECT * FROM membership_applications WHERE lower(email) = :email ORDER BY submitted_at DESC');
$stmt->execute([':email' => $email]);
$records = $stmt->fetchAll();
if (!$records) {
    fwrite(STDERR, "No membership application found for {$email}.\n");
    exit(1);
}

if ($command === 'export') {
    $output = [];
    foreach ($records as $record) {
        $audit = $db->prepare('SELECT event_type, event_at, details FROM membership_audit WHERE application_uuid = :uuid ORDER BY event_at');
        $audit->execute([':uuid' => $record['application_uuid']]);
        $record['audit'] = $audit->fetchAll();
        $output[] = $record;
    }
    echo json_encode(['exported_at' => gmdate('c'), 'records' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if ($command === 'correct') {
    $field = (string) ($options['field'] ?? '');
    $value = (string) ($options['value'] ?? '');
    $allowed = [
        'first_name', 'last_name', 'preferred_name', 'date_of_birth', 'address', 'postcode',
        'email', 'phone', 'preferred_contact', 'membership_section', 'emergency_name',
        'emergency_relationship', 'emergency_phone', 'second_emergency_name',
        'second_emergency_phone', 'health_details', 'photo_consent'
    ];
    if (!in_array($field, $allowed, true)) {
        fwrite(STDERR, "Field is not permitted for correction through this tool.\n");
        exit(2);
    }

    $db->beginTransaction();
    try {
        foreach ($records as $record) {
            $stmt = $db->prepare("UPDATE membership_applications SET {$field} = :value, updated_at = :updated WHERE id = :id");
            $stmt->execute([':value' => $value, ':updated' => gmdate('c'), ':id' => $record['id']]);
            hwfcAudit($db, $record['application_uuid'], 'data_corrected', ['field' => $field]);
        }
        $db->commit();
        echo "Corrected {$field} for " . count($records) . " record(s).\n";
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    exit(0);
}

if ($command === 'delete') {
    if (!array_key_exists('confirm', $options)) {
        fwrite(STDERR, "Deletion is destructive. Re-run with --confirm after checking the export and retention need.\n");
        exit(2);
    }

    $db->beginTransaction();
    try {
        foreach ($records as $record) {
            $uuid = $record['application_uuid'];
            $stmt = $db->prepare('DELETE FROM membership_applications WHERE id = :id');
            $stmt->execute([':id' => $record['id']]);
            hwfcAudit($db, $uuid, 'personal_data_deleted', ['reason' => 'GDPR/process deletion via CLI']);
        }
        $db->commit();
        echo "Deleted personal data for " . count($records) . " record(s). Non-personal audit events remain.\n";
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    exit(0);
}

usage();
