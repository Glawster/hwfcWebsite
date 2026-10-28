<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/adminDevAccess.php';
require_once __DIR__ . '/../members/lib/memberDb.php';

hwfcRequireAdminDevelopmentAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$csrf = (string) ($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
    http_response_code(400);
    exit('Invalid admin form session.');
}

$uuid = trim((string) ($_POST['id'] ?? ''));
$notes = trim((string) ($_POST['adminNotes'] ?? ''));

if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    exit('Invalid request.');
}

$notesLength = function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes);
if ($notesLength > 5000) {
    $_SESSION['admin_error'] = 'Notes must be 5,000 characters or fewer.';
    header('Location: /admin/member.php?id=' . rawurlencode($uuid));
    exit;
}

try {
    $db = hwfcMemberDb();
    $stmt = $db->prepare('SELECT admin_notes AS adminNotes FROM membership_applications WHERE application_uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $uuid]);
    $application = $stmt->fetch();

    if (!$application) {
        throw new RuntimeException('Application not found.');
    }

    $previous = trim((string) ($application['adminNotes'] ?? ''));
    if ($previous !== $notes) {
        $update = $db->prepare('UPDATE membership_applications SET admin_notes = :notes, updated_at = :updated_at WHERE application_uuid = :uuid');
        $update->execute([
            ':notes' => $notes !== '' ? $notes : null,
            ':updated_at' => gmdate('c'),
            ':uuid' => $uuid,
        ]);

        hwfcAudit($db, $uuid, 'admin_notes_updated', [
            'hadNotesBefore' => $previous !== '',
            'hasNotesAfter' => $notes !== '',
            'actor' => 'local-development-admin',
        ]);
    }

    $_SESSION['admin_flash'] = 'Notes saved.';
} catch (Throwable $e) {
    error_log('Admin notes update failed: ' . $e->getMessage());
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: /admin/member.php?id=' . rawurlencode($uuid));
exit;
