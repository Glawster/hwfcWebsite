<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/memberDb.php';
require_once __DIR__ . '/lib/memberValidation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /members/');
    exit;
}

if (!hash_equals($_SESSION['member_csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Invalid form session. Please return to the membership form and try again.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    header('Location: /members/thanks.php');
    exit;
}

$input = [];
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $input[$key] = trim($value);
    }
}

$errors = hwfcValidateApplication($input);
if ($errors) {
    $_SESSION['member_errors'] = $errors;
    $_SESSION['member_old'] = $input;
    header('Location: /members/#application');
    exit;
}

try {
    $db = hwfcMemberDb();
    $uuid = bin2hex(random_bytes(16));
    $now = gmdate('c');

    $stmt = $db->prepare(<<<'SQL'
INSERT INTO membership_applications (
    application_uuid, first_name, last_name, preferred_name, date_of_birth,
    address, postcode, email, phone, preferred_contact,
    emergency_name, emergency_relationship, emergency_phone,
    second_emergency_name, second_emergency_relationship, second_emergency_phone,
    health_relevant, health_details, photo_consent,
    privacy_version, submitted_at, updated_at, status
) VALUES (
    :uuid, :first_name, :last_name, :preferred_name, :dob,
    :address, :postcode, :email, :phone, :preferred_contact,
    :emergency_name, :emergency_relationship, :emergency_phone,
    :second_emergency_name, :second_emergency_relationship, :second_emergency_phone,
    :health_relevant, :health_details, :photo_consent,
    :privacy_version, :submitted_at, :updated_at, 'Pending'
)
SQL);

    $stmt->execute([
        ':uuid' => $uuid,
        ':first_name' => $input['first_name'],
        ':last_name' => $input['last_name'],
        ':preferred_name' => $input['preferred_name'] ?: null,
        ':dob' => $input['date_of_birth'],
        ':address' => $input['address'],
        ':postcode' => strtoupper($input['postcode']),
        ':email' => strtolower($input['email']),
        ':phone' => $input['phone'],
        ':preferred_contact' => $input['preferred_contact'],
        ':emergency_name' => $input['emergency_name'],
        ':emergency_relationship' => $input['emergency_relationship'],
        ':emergency_phone' => $input['emergency_phone'],
        ':second_emergency_name' => ($input['second_emergency_name'] ?? '') !== '' ? $input['second_emergency_name'] : null,
        ':second_emergency_relationship' => ($input['second_emergency_relationship'] ?? '') !== '' ? $input['second_emergency_relationship'] : null,
        ':second_emergency_phone' => ($input['second_emergency_phone'] ?? '') !== '' ? $input['second_emergency_phone'] : null,
        ':health_relevant' => $input['health_relevant'] === 'yes' ? 1 : 0,
        ':health_details' => $input['health_relevant'] === 'yes' ? $input['health_details'] : null,
        ':photo_consent' => $input['photo_consent'] === 'yes' ? 1 : 0,
        ':privacy_version' => hwfcPrivacyVersion(),
        ':submitted_at' => $now,
        ':updated_at' => $now,
    ]);

    hwfcAudit($db, $uuid, 'application_submitted', ['privacy_version' => hwfcPrivacyVersion()]);
    unset($_SESSION['member_old'], $_SESSION['member_errors']);
    $_SESSION['member_application_reference'] = strtoupper(substr($uuid, 0, 10));
    header('Location: /members/thanks.php');
    exit;
} catch (Throwable $e) {
    error_log('Membership application storage failed: ' . $e->getMessage());
    http_response_code(500);
    exit('We could not save your application. Please try again later or contact HWFC.');
}
