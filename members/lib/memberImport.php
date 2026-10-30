<?php

declare(strict_types=1);

require_once __DIR__ . '/memberDb.php';

/*
 * Imports existing members from the "Hillsborough Walking Football Club – Member Responses"
 * Google Form spreadsheet (XLSX or CSV download). Imported rows become Pending applications
 * so Membership IDs are still allocated through the normal Accept workflow. Existing records
 * are never updated: duplicates and invalid rows are reported and skipped (REQ-004).
 */

const HWFC_MEMBER_IMPORT_SOURCE = 'Member Responses Google Form';
const HWFC_MEMBER_IMPORT_MAX_BYTES = 2 * 1024 * 1024;
const HWFC_MEMBER_IMPORT_PLACEHOLDER_DOB = '1970-01-01';

const HWFC_MEMBER_IMPORT_COLUMNS = [
    'timestamp' => 'Timestamp',
    'fullName' => 'Full name',
    'preferredName' => 'Preferred name (if different)',
    'dateOfBirth' => 'Date of birth',
    'address' => 'Home address',
    'postcode' => 'Postcode',
    'phone' => 'Telephone number',
    'email' => 'Email address',
    'preferredContact' => 'Preferred method of contact',
    'emergencyName' => 'Emergency contact full name',
    'emergencyRelationship' => 'Relationship to you',
    'emergencyPhone' => 'Emergency contact telephone number',
    'secondEmergencyName' => 'Second emergency contact name',
    'secondEmergencyPhone' => 'Second emergency contact telephone number',
    'medical' => 'Do you have a medical condition that may affect your participation or require assistance?',
    'medicalDetails' => 'If yes, what would you like us to know?',
    'allergies' => 'Do you have any allergies that may require emergency assistance?',
    'allergyDetails' => 'If yes, please provide relevant allergy details.',
    'medication' => 'Do you carry emergency medication that we should know about?',
    'medicationDetails' => 'If yes, what is it and where is it kept during sessions?',
    'mobility' => 'Do you have any mobility, balance, hearing or sight requirements we should accommodate?',
    'otherSupport' => 'Is there anything else that would help us support you safely?',
    'membershipType' => 'Membership type',
    'clubNews' => 'Would you like to receive club news and updates?',
    'whatsApp' => 'Are you happy to be added to the club WhatsApp group?',
    'photoConsent' => 'Can we use photographs or videos featuring you on our social media or website?',
    'helping' => 'Would you be interested in helping with club activities or events?',
    'accuracyConfirmed' => 'I confirm that the information I have provided is accurate.',
    'changesConfirmed' => 'I will inform the club if my emergency contacts or relevant health information change.',
    'riskConfirmed' => 'I understand that walking football is a physical activity and that I should seek medical advice if I am unsure whether I can participate safely.',
    'privacyAcknowledged' => 'I acknowledge that I have been provided with the club privacy notice.',
];

/** Every column of the Google Form responses sheet, in order, as written by the CSV export. */
const HWFC_MEMBER_RESPONSES_HEADINGS = [
    'timestamp' => 'Timestamp',
    'fullName' => 'Full name',
    'preferredName' => 'Preferred name (if different)',
    'dateOfBirth' => 'Date of birth',
    'address' => 'Home address',
    'postcode' => 'Postcode',
    'phone' => 'Telephone number',
    'email' => 'Email address',
    'preferredContact' => 'Preferred method of contact',
    'emergencyName' => 'Emergency contact full name',
    'emergencyRelationship' => 'Relationship to you',
    'emergencyPhone' => 'Emergency contact telephone number',
    'secondEmergencyName' => 'Second emergency contact name',
    'secondEmergencyPhone' => 'Second emergency contact telephone number',
    'medical' => 'Do you have a medical condition that may affect your participation or require assistance?',
    'medicalDetails' => 'If yes, what would you like us to know?',
    'allergies' => 'Do you have any allergies that may require emergency assistance?',
    'allergyDetails' => 'If yes, please provide relevant allergy details.',
    'medication' => 'Do you carry emergency medication that we should know about?',
    'medicationDetails' => 'If yes, what is it and where is it kept during sessions?',
    'mobility' => 'Do you have any mobility, balance, hearing or sight requirements we should accommodate?',
    'otherSupport' => 'Is there anything else that would help us support you safely?',
    'membershipType' => 'Membership type',
    'clubNews' => 'Would you like to receive club news and updates?',
    'whatsApp' => 'Are you happy to be added to the club WhatsApp group?',
    'photoConsent' => 'Can we use photographs or videos featuring you on our social media or website?',
    'helping' => 'Would you be interested in helping with club activities or events?',
    'accuracyConfirmed' => 'I confirm that the information I have provided is accurate.',
    'changesConfirmed' => 'I will inform the club if my emergency contacts or relevant health information change.',
    'riskConfirmed' => 'I understand that walking football is a physical activity and that I should seek medical advice if I am unsure whether I can participate safely.',
    'privacyAcknowledged' => 'I acknowledge that I have been provided with the club privacy notice.',
    'formEmail' => 'Email Address',
    'membershipStartDate' => 'Membership start date',
    'membershipId' => 'Membership ID',
    'status' => 'Status',
    'membershipExpiryDate' => 'Membership expiry date',
    'paymentPlan' => 'Payment plan',
    'paymentStatus' => 'Payment status',
    'lastPaymentDate' => 'Last payment date',
    'paidThroughDate' => 'Paid through date',
    'nextPaymentDueDate' => 'Next payment due date',
    'secondEmergencyRelationship' => 'Second emergency contact relationship',
    'applicationReference' => 'Application reference',
];

/**
 * Columns the club may add to the form sheet, or that come from the export; blank or absent
 * values are allowed. Status, Next payment due date and Application reference are export-only.
 */
const HWFC_MEMBER_IMPORT_OPTIONAL_COLUMNS = [
    'membershipStartDate' => 'Membership start date',
    'membershipId' => 'Membership ID',
    'status' => 'Status',
    'membershipExpiryDate' => 'Membership expiry date',
    'paymentPlan' => 'Payment plan',
    'paymentStatus' => 'Payment status',
    'lastPaymentDate' => 'Last payment date',
    'paidThroughDate' => 'Paid through date',
    'secondEmergencyRelationship' => 'Second emergency contact relationship',
];

const HWFC_MEMBER_PAYMENT_PLANS = ['Not Set', 'Annual', 'Monthly'];
const HWFC_MEMBER_PAYMENT_STATUSES = ['Not Recorded', 'Current', 'Payment Due', 'Overdue', 'Paid In Full', 'Waived'];

function hwfcMemberImportReadFile(string $path): array
{
    $size = filesize($path);
    if ($size === false || $size === 0) {
        throw new RuntimeException('The uploaded spreadsheet is empty.');
    }
    if ($size > HWFC_MEMBER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('The uploaded spreadsheet is unexpectedly large.');
    }

    $handle = fopen($path, 'rb');
    $signature = $handle === false ? '' : (string) fread($handle, 4);
    if ($handle !== false) {
        fclose($handle);
    }

    return $signature === "PK\x03\x04" ? hwfcReadXlsxRows($path) : hwfcReadCsvRows($path);
}

function hwfcReadCsvRows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The uploaded spreadsheet could not be opened.');
    }

    $rows = [];
    $rowNumber = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
        // Undo the export's spreadsheet-formula guard (a leading apostrophe) so exported files re-import cleanly.
        $values = array_map(static fn($value): string => preg_replace('/^\'(?=[=+\-@])/', '', trim((string) $value)) ?? '', $row);
        if ($rowNumber === 1 && isset($values[0])) {
            $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) ?? $values[0];
        }
        $rows[$rowNumber] = $values;
    }
    fclose($handle);

    return $rows;
}

/** Returns the first worksheet as [spreadsheet row number => [column index => string]]. */
function hwfcReadXlsxRows(string $path): array
{
    $parts = hwfcZipRead($path, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml']);
    if (!isset($parts['xl/workbook.xml'], $parts['xl/_rels/workbook.xml.rels'])) {
        throw new RuntimeException('This does not look like an Excel spreadsheet.');
    }

    if (!preg_match('/<sheet\b[^>]*\br:id="([^"]+)"/', $parts['xl/workbook.xml'], $sheetMatch)) {
        throw new RuntimeException('The spreadsheet does not contain a worksheet.');
    }
    $sheetPath = null;
    preg_match_all('/<Relationship\b[^>]*>/', $parts['xl/_rels/workbook.xml.rels'], $relationships);
    foreach ($relationships[0] as $relationship) {
        if (preg_match('/\bId="([^"]+)"/', $relationship, $id) && $id[1] === $sheetMatch[1]
            && preg_match('/\bTarget="([^"]+)"/', $relationship, $target)) {
            $sheetPath = str_starts_with($target[1], '/') ? ltrim($target[1], '/') : 'xl/' . $target[1];
        }
    }
    if ($sheetPath === null) {
        throw new RuntimeException('The spreadsheet worksheet could not be located.');
    }

    $sheet = hwfcZipRead($path, [$sheetPath])[$sheetPath] ?? null;
    if ($sheet === null) {
        throw new RuntimeException('The spreadsheet worksheet could not be read.');
    }

    $sharedStrings = [];
    if (isset($parts['xl/sharedStrings.xml'])) {
        preg_match_all('/<si>(.*?)<\/si>/s', $parts['xl/sharedStrings.xml'], $items);
        foreach ($items[1] as $item) {
            $sharedStrings[] = hwfcXlsxText($item);
        }
    }

    $rows = [];
    preg_match_all('/<row\b([^>]*)>(.*?)<\/row>/s', $sheet, $rowMatches, PREG_SET_ORDER);
    foreach ($rowMatches as $position => $rowMatch) {
        $rowNumber = preg_match('/\br="(\d+)"/', $rowMatch[1], $r) ? (int) $r[1] : $position + 1;
        $values = [];
        preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $rowMatch[2], $cells, PREG_SET_ORDER);
        foreach ($cells as $cellIndex => $cell) {
            $column = preg_match('/\br="([A-Z]+)\d+"/', $cell[1], $ref) ? hwfcXlsxColumnIndex($ref[1]) : $cellIndex;
            $type = preg_match('/\bt="([^"]+)"/', $cell[1], $t) ? $t[1] : 'n';
            $body = $cell[2] ?? '';
            if ($type === 'inlineStr') {
                $value = hwfcXlsxText($body);
            } else {
                $value = preg_match('/<v>(.*?)<\/v>/s', $body, $v) ? html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
            }
            $values[$column] = trim($value);
        }
        $rows[$rowNumber] = $values;
    }

    return $rows;
}

function hwfcXlsxText(string $xml): string
{
    $xml = preg_replace('/<rPh\b.*?<\/rPh>/s', '', $xml) ?? $xml;
    preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $xml, $texts);
    return html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function hwfcXlsxColumnIndex(string $letters): int
{
    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = $index * 26 + (ord($letter) - 64);
    }
    return $index - 1;
}

/** Minimal ZIP reader so XLSX import does not depend on the ZipArchive extension. */
function hwfcZipRead(string $path, array $wanted): array
{
    $data = (string) file_get_contents($path);
    $endOfDirectory = strrpos($data, "PK\x05\x06");
    if ($endOfDirectory === false || strlen($data) < $endOfDirectory + 22) {
        throw new RuntimeException('The spreadsheet file is damaged or is not an XLSX file.');
    }

    $end = unpack('vdisk/vdirectoryDisk/ventriesHere/ventries/VdirectorySize/VdirectoryOffset', substr($data, $endOfDirectory + 4, 16));
    $position = $end['directoryOffset'];
    $found = [];
    for ($entry = 0; $entry < $end['entries']; $entry++) {
        if (substr($data, $position, 4) !== "PK\x01\x02") {
            throw new RuntimeException('The spreadsheet file is damaged.');
        }
        $header = unpack('vmadeBy/vneeded/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/VsizeUncompressed/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternal/Vexternal/VlocalOffset', substr($data, $position + 4, 42));
        $name = substr($data, $position + 46, $header['nameLength']);
        $position += 46 + $header['nameLength'] + $header['extraLength'] + $header['commentLength'];
        if (!in_array($name, $wanted, true)) {
            continue;
        }
        if ($header['sizeUncompressed'] > 8 * HWFC_MEMBER_IMPORT_MAX_BYTES) {
            throw new RuntimeException('The spreadsheet contains unexpectedly large content.');
        }

        $local = unpack('vnameLength/vextraLength', substr($data, $header['localOffset'] + 26, 4));
        $raw = substr($data, $header['localOffset'] + 30 + $local['nameLength'] + $local['extraLength'], $header['compressedSize']);
        $content = match ($header['method']) {
            0 => $raw,
            8 => @gzinflate($raw, 8 * HWFC_MEMBER_IMPORT_MAX_BYTES),
            default => false,
        };
        if ($content === false) {
            throw new RuntimeException('The spreadsheet uses an unsupported compression format.');
        }
        $found[$name] = $content;
    }

    return $found;
}

/**
 * Maps spreadsheet rows to import candidates. Each candidate carries the database values,
 * a preview summary and its errors. Throws when the header row does not match the template.
 */
function hwfcMemberImportCandidates(array $rows, ?string $defaultStartDate = null): array
{
    $headerRowNumber = array_key_first($rows);
    if ($headerRowNumber === null) {
        throw new RuntimeException('The spreadsheet has no rows.');
    }

    $positions = [];
    foreach ($rows[$headerRowNumber] as $index => $heading) {
        $normalised = hwfcMemberImportNormaliseHeading((string) $heading);
        if ($normalised !== '' && !isset($positions[$normalised])) {
            $positions[$normalised] = $index;
        }
    }

    $columns = [];
    $missing = [];
    foreach (HWFC_MEMBER_IMPORT_COLUMNS as $key => $heading) {
        $normalised = hwfcMemberImportNormaliseHeading($heading);
        if (isset($positions[$normalised])) {
            $columns[$key] = $positions[$normalised];
        } else {
            $missing[] = $heading;
        }
    }
    foreach (HWFC_MEMBER_IMPORT_OPTIONAL_COLUMNS as $key => $heading) {
        $normalised = hwfcMemberImportNormaliseHeading($heading);
        if (isset($positions[$normalised])) {
            $columns[$key] = $positions[$normalised];
        }
    }
    if ($missing) {
        throw new RuntimeException('The spreadsheet does not match the member responses template. Missing columns: ' . implode('; ', $missing));
    }

    $candidates = [];
    foreach ($rows as $rowNumber => $row) {
        if ($rowNumber === $headerRowNumber || implode('', $row) === '') {
            continue;
        }
        $values = array_fill_keys(array_keys(HWFC_MEMBER_IMPORT_OPTIONAL_COLUMNS), '');
        foreach ($columns as $key => $index) {
            $values[$key] = trim((string) ($row[$index] ?? ''));
        }
        $candidates[] = hwfcMemberImportCandidate((int) $rowNumber, $values, $defaultStartDate);
    }

    if (!$candidates) {
        throw new RuntimeException('The spreadsheet does not contain any member responses.');
    }

    return $candidates;
}

function hwfcMemberImportNormaliseHeading(string $heading): string
{
    return strtolower(preg_replace('/\s+/u', ' ', trim($heading)) ?? '');
}

/** Restores the apostrophe Google Form answers often drop from Irish surnames: OHara becomes O'Hara. */
function hwfcMemberImportName(string $name): string
{
    return preg_replace("/(?<![\\p{L}'])O(?=\\p{Lu}\\p{Ll})/u", "O'", $name) ?? $name;
}

function hwfcMemberImportCandidate(int $rowNumber, array $v, ?string $defaultStartDate = null): array
{
    $errors = [];
    foreach (['fullName', 'preferredName', 'emergencyName', 'secondEmergencyName'] as $nameKey) {
        $v[$nameKey] = hwfcMemberImportName($v[$nameKey]);
    }

    $nameParts = preg_split('/\s+/u', $v['fullName'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lastName = count($nameParts) >= 2 ? (string) array_pop($nameParts) : '';
    $firstName = implode(' ', $nameParts);
    if ($lastName === '') {
        $errors[] = 'Full name must include a first and last name.';
    }

    $submittedAt = hwfcMemberImportDateTime($v['timestamp']);
    if ($submittedAt === null) {
        $errors[] = 'Timestamp is missing or invalid.';
    }

    // A missing date of birth is imported as a placeholder for the club to amend on the member record.
    $warnings = [];
    $dateOfBirthMissing = $v['dateOfBirth'] === '';
    $dateOfBirth = $dateOfBirthMissing ? HWFC_MEMBER_IMPORT_PLACEHOLDER_DOB : hwfcMemberImportDate($v['dateOfBirth']);
    if ($dateOfBirthMissing) {
        $warnings[] = 'Date of birth is missing; 01/01/1970 will be used until it is amended.';
    } elseif ($dateOfBirth === null) {
        $errors[] = 'Date of birth is invalid.';
    } elseif ($dateOfBirth > (new DateTimeImmutable('today'))->modify('-16 years')->format('Y-m-d')) {
        $errors[] = 'Date of birth looks wrong (the member would be under 16).';
    }

    // Email addresses cannot contain whitespace; form responses sometimes include a stray space.
    $v['email'] = strtolower(preg_replace('/\s+/u', '', $v['email']) ?? $v['email']);

    $startDate = null;
    $expiryDate = null;
    if ($v['membershipStartDate'] !== '') {
        $startDate = hwfcMemberImportDate($v['membershipStartDate'], true);
        if ($startDate === null) {
            $errors[] = 'Membership start date is invalid.';
        }
    } elseif ($defaultStartDate !== null) {
        $startDate = $defaultStartDate;
    }
    if ($v['membershipExpiryDate'] !== '') {
        $expiryDate = hwfcMemberImportDate($v['membershipExpiryDate'], true);
        if ($expiryDate === null) {
            $errors[] = 'Membership expiry date is invalid.';
        } elseif ($startDate === null || $expiryDate < $startDate) {
            $errors[] = 'Membership expiry date needs a start date on or before it.';
        }
    } elseif ($startDate !== null) {
        $expiryDate = hwfcMemberAnnualExpiry($startDate);
    }

    $required = [
        'address' => 'Home address',
        'postcode' => 'Postcode',
        'phone' => 'Telephone number',
        'email' => 'Email address',
        'emergencyName' => 'Emergency contact name',
        'emergencyRelationship' => 'Emergency contact relationship',
        'emergencyPhone' => 'Emergency contact telephone number',
    ];
    foreach ($required as $key => $label) {
        if ($v[$key] === '') {
            $errors[] = $label . ' is missing.';
        }
    }
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is invalid.';
    }

    $preferredContact = match (strtolower($v['preferredContact'])) {
        'text message', 'text' => 'text',
        'whatsapp' => 'whatsapp',
        'phone call', 'phone', 'telephone' => 'phone',
        'email' => 'email',
        default => null,
    };
    if ($preferredContact === null) {
        $errors[] = 'Preferred method of contact is not recognised.';
    }

    if (($v['secondEmergencyName'] === '') !== ($v['secondEmergencyPhone'] === '')) {
        $errors[] = 'Second emergency contact needs both a name and a telephone number.';
    }

    $healthLines = array_values(array_filter([
        hwfcMemberImportHealthLine('Medical condition', $v['medical'], $v['medicalDetails']),
        hwfcMemberImportHealthLine('Allergies', $v['allergies'], $v['allergyDetails']),
        hwfcMemberImportHealthLine('Emergency medication', $v['medication'], $v['medicationDetails']),
        hwfcMemberImportHealthLine('Mobility, balance, hearing or sight', $v['mobility'], ''),
        hwfcMemberImportHealthLine('Other safety information', $v['otherSupport'], ''),
    ]));

    $notes = array_filter([
        'Imported from the ' . HWFC_MEMBER_IMPORT_SOURCE . ' (spreadsheet row ' . $rowNumber . ').',
        $dateOfBirthMissing ? 'Date of birth was missing from the form; 01/01/1970 is a placeholder and must be amended.' : null,
    ]);
    $formAnswers = hwfcFormAnswersBuild(array_intersect_key($v, HWFC_MEMBER_FORM_ANSWER_LABELS));

    return [
        'rowNumber' => $rowNumber,
        'result' => $errors ? 'Invalid' : 'New',
        'messages' => $errors ?: $warnings,
        'summary' => [
            'name' => trim($firstName . ' ' . $lastName) ?: $v['fullName'],
            'email' => $v['email'],
            'dateOfBirth' => $dateOfBirth ?? $v['dateOfBirth'],
            'membershipType' => $v['membershipType'],
            'healthInformation' => $healthLines !== [],
            'membershipStartDate' => $startDate,
        ],
        'record' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'preferred_name' => $v['preferredName'] !== '' ? $v['preferredName'] : null,
            'date_of_birth' => $dateOfBirth,
            'address' => $v['address'],
            'postcode' => strtoupper($v['postcode']),
            'email' => $v['email'],
            'phone' => $v['phone'],
            'preferred_contact' => $preferredContact,
            'emergency_name' => $v['emergencyName'],
            'emergency_relationship' => $v['emergencyRelationship'],
            'emergency_phone' => $v['emergencyPhone'],
            'second_emergency_name' => $v['secondEmergencyName'] !== '' ? $v['secondEmergencyName'] : null,
            'second_emergency_phone' => $v['secondEmergencyPhone'] !== '' ? $v['secondEmergencyPhone'] : null,
            'second_emergency_relationship' => $v['secondEmergencyRelationship'] !== '' ? $v['secondEmergencyRelationship'] : null,
            'health_relevant' => $healthLines ? 1 : 0,
            'health_details' => $healthLines ? implode("\n", $healthLines) : null,
            'photo_consent' => strtolower($v['photoConsent']) === 'yes' ? 1 : 0,
            'admin_notes' => implode("\n", $notes),
            'form_answers' => $formAnswers,
            'membership_start_date' => $startDate,
            'membership_expiry_date' => $expiryDate,
            'submitted_at' => $submittedAt,
        ],
        'privacyAcknowledged' => strtolower($v['privacyAcknowledged']) === 'i confirm',
        'values' => $v,
    ];
}

function hwfcMemberAnnualExpiry(string $startDate): string
{
    return (new DateTimeImmutable($startDate))->modify('+1 year -1 day')->format('Y-m-d');
}

function hwfcMemberImportIsNone(string $answer): bool
{
    return preg_match('/^(no|none|nope|nil|nothing|n\/?a|no thanks?|no thank you)?[.!]?$/i', trim($answer)) === 1;
}

function hwfcMemberImportHealthLine(string $label, string $answer, string $details): ?string
{
    $details = hwfcMemberImportIsNone($details) ? '' : $details;
    if ($details !== '') {
        return $label . ': ' . $details;
    }
    if (hwfcMemberImportIsNone($answer)) {
        return null;
    }
    return $label . ': ' . (strtolower($answer) === 'yes' ? 'Yes (no details given)' : $answer);
}

/** Converts a spreadsheet date (Excel serial or d/m/Y text) to Y-m-d, rejecting future dates. */
function hwfcMemberImportDate(string $value, bool $allowFuture = false): ?string
{
    if ($value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $date = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $value) . ' days');
    } else {
        $date = false;
        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $parseErrors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($parseErrors === false || ($parseErrors['warning_count'] === 0 && $parseErrors['error_count'] === 0))) {
                break;
            }
            $date = false;
        }
    }
    if ($date === false || (!$allowFuture && $date > new DateTimeImmutable('today')) || (int) $date->format('Y') < 1900) {
        return null;
    }
    return $date->format('Y-m-d');
}

/** Converts a Google Form timestamp (Excel serial or d/m/Y H:i:s text, UK local time) to UTC ISO 8601. */
function hwfcMemberImportDateTime(string $value): ?string
{
    $zone = new DateTimeZone('Europe/London');
    if (is_numeric($value)) {
        $seconds = (int) round((float) $value * 86400);
        $timeOfDay = $seconds % 86400;
        $date = (new DateTimeImmutable('1899-12-30 00:00:00', $zone))
            ->modify('+' . intdiv($seconds, 86400) . ' days')
            ->setTime(intdiv($timeOfDay, 3600), intdiv($timeOfDay % 3600, 60), $timeOfDay % 60);
    } else {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y H:i:s', $value, $zone);
    }
    if ($date === false || $value === '') {
        return null;
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('c');
}

/**
 * Marks candidates that match an existing member or an earlier row in the same file. Rows with a
 * Membership ID are matched by that ID: in update mode they become Update or Unchanged rows,
 * otherwise they are Duplicates.
 */
function hwfcMemberImportMarkDuplicates(PDO $db, array $candidates, bool $updateMode = false): array
{
    $seen = [];
    $seenIds = [];
    foreach ($candidates as &$candidate) {
        $membershipId = strtoupper(trim((string) ($candidate['values']['membershipId'] ?? '')));
        if ($membershipId !== '') {
            $candidate = hwfcMemberImportMatchMembershipId($db, $candidate, $membershipId, $updateMode, $seenIds);
            $seenIds[$membershipId] ??= $candidate['rowNumber'];
            continue;
        }
        if ($candidate['result'] === 'Invalid') {
            continue;
        }
        $record = $candidate['record'];
        $nameKey = strtolower($record['first_name'] . ' ' . $record['last_name']);
        $keys = [$nameKey . '|dob|' . $record['date_of_birth'], $nameKey . '|email|' . $record['email']];

        $existing = hwfcMemberImportExistingMatch($db, $record);
        if ($existing !== null) {
            $candidate['result'] = 'Duplicate';
            $candidate['messages'] = ['Matches existing member ' . $existing . '; the existing record will not be changed.'];
        } elseif (array_intersect($keys, array_keys($seen))) {
            $candidate['result'] = 'Duplicate';
            $candidate['messages'] = ['Repeats spreadsheet row ' . $seen[array_values(array_intersect($keys, array_keys($seen)))[0]] . '.'];
        }
        foreach ($keys as $key) {
            $seen[$key] ??= $candidate['rowNumber'];
        }
    }
    unset($candidate);

    return $candidates;
}

function hwfcMemberImportExistingMatch(PDO $db, array $record): ?string
{
    $stmt = $db->prepare(<<<'SQL'
SELECT application_uuid AS applicationUuid, membership_id AS membershipId
FROM membership_applications
WHERE lower(first_name) = lower(:first_name)
  AND lower(last_name) = lower(:last_name)
  AND (date_of_birth = :date_of_birth OR lower(email) = lower(:email))
LIMIT 1
SQL);
    $stmt->execute([
        ':first_name' => $record['first_name'],
        ':last_name' => $record['last_name'],
        ':date_of_birth' => $record['date_of_birth'],
        ':email' => $record['email'],
    ]);
    $match = $stmt->fetch();
    if (!$match) {
        return null;
    }
    return $match['membershipId'] ?: strtoupper(substr((string) $match['applicationUuid'], 0, 10));
}

/**
 * Inserts New candidates as Pending applications and applies Update candidates in one
 * transaction. Returns [imported, updated, skipped].
 */
function hwfcMemberImportCommit(PDO $db, array $candidates, string $fileName, string $actor, bool $updateMode = false): array
{
    $insert = $db->prepare(<<<'SQL'
INSERT INTO membership_applications (
    application_uuid, first_name, last_name, preferred_name, date_of_birth,
    address, postcode, email, phone, preferred_contact,
    emergency_name, emergency_relationship, emergency_phone,
    second_emergency_name, second_emergency_phone, second_emergency_relationship,
    health_relevant, health_details, photo_consent,
    privacy_version, admin_notes, form_answers, membership_start_date, membership_expiry_date,
    submitted_at, updated_at, status
) VALUES (
    :uuid, :first_name, :last_name, :preferred_name, :date_of_birth,
    :address, :postcode, :email, :phone, :preferred_contact,
    :emergency_name, :emergency_relationship, :emergency_phone,
    :second_emergency_name, :second_emergency_phone, :second_emergency_relationship,
    :health_relevant, :health_details, :photo_consent,
    :privacy_version, :admin_notes, :form_answers, :membership_start_date, :membership_expiry_date,
    :submitted_at, :updated_at, 'Pending'
)
SQL);

    $previewedAt = [];
    foreach ($candidates as $candidate) {
        if (isset($candidate['update'])) {
            $previewedAt[$candidate['rowNumber']] = $candidate['update']['updatedAt'];
        }
    }

    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $db->beginTransaction();
    try {
        // Re-check against the database inside the transaction so a stale preview cannot create duplicates.
        foreach (hwfcMemberImportMarkDuplicates($db, $candidates, $updateMode) as $candidate) {
            if ($candidate['result'] === 'Update') {
                // Skip members edited on the website since the preview rather than apply changes nobody reviewed.
                if (($previewedAt[$candidate['rowNumber']] ?? null) !== $candidate['update']['updatedAt']) {
                    $skipped++;
                    continue;
                }
                hwfcMemberImportApplyUpdate($db, $candidate, $fileName, $actor);
                $updated++;
                continue;
            }
            if ($candidate['result'] !== 'New') {
                $skipped++;
                continue;
            }
            $uuid = bin2hex(random_bytes(16));
            $now = gmdate('c');
            $params = [':uuid' => $uuid, ':privacy_version' => HWFC_MEMBER_IMPORT_SOURCE, ':updated_at' => $now];
            foreach ($candidate['record'] as $column => $value) {
                $params[':' . $column] = $value;
            }
            $insert->execute($params);

            hwfcAudit($db, $uuid, 'member_imported', [
                'source' => HWFC_MEMBER_IMPORT_SOURCE,
                'fileName' => $fileName,
                'spreadsheetRow' => $candidate['rowNumber'],
                'privacyNoticeAcknowledged' => $candidate['privacyAcknowledged'],
                'actor' => $actor,
            ]);
            $imported++;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return [$imported, $updated, $skipped];
}

/**
 * Builds member responses rows (heading row first) for every non-rejected member, in the
 * Google Form layout so the file can be edited offline and read by the importer.
 */
function hwfcMemberExportRows(PDO $db): array
{
    $members = $db->query("SELECT * FROM membership_applications WHERE status <> 'Rejected' ORDER BY submitted_at, id")->fetchAll();

    $rows = [array_values(HWFC_MEMBER_RESPONSES_HEADINGS)];
    foreach ($members as $member) {
        $rows[] = array_map('hwfcMemberExportCell', array_values(hwfcMemberExportValues($member)));
    }

    return $rows;
}

/** Maps one membership_applications row to the export columns, keyed as HWFC_MEMBER_RESPONSES_HEADINGS. */
function hwfcMemberExportValues(array $member): array
{
    $values = array_fill_keys(array_keys(HWFC_MEMBER_RESPONSES_HEADINGS), '');
    $values['timestamp'] = hwfcMemberExportDateTime((string) $member['submitted_at']);
    $values['fullName'] = trim($member['first_name'] . ' ' . $member['last_name']);
    $values['preferredName'] = (string) $member['preferred_name'];
    $values['dateOfBirth'] = hwfcMemberExportDate($member['date_of_birth']);
    $values['address'] = (string) $member['address'];
    $values['postcode'] = (string) $member['postcode'];
    $values['phone'] = (string) $member['phone'];
    $values['email'] = (string) $member['email'];
    $values['preferredContact'] = match ((string) $member['preferred_contact']) {
        'text' => 'Text message',
        'whatsapp' => 'WhatsApp',
        'phone' => 'Phone call',
        'email' => 'Email',
        default => (string) $member['preferred_contact'],
    };
    $values['emergencyName'] = (string) $member['emergency_name'];
    $values['emergencyRelationship'] = (string) $member['emergency_relationship'];
    $values['emergencyPhone'] = (string) $member['emergency_phone'];
    $values['secondEmergencyName'] = (string) $member['second_emergency_name'];
    $values['secondEmergencyPhone'] = (string) $member['second_emergency_phone'];
    $values = array_merge($values, hwfcMemberExportHealth((int) $member['health_relevant'], (string) $member['health_details']));

    $answers = hwfcFormAnswersParse($member['form_answers'] ?? null);
    foreach (array_keys(HWFC_MEMBER_FORM_ANSWER_LABELS) as $key) {
        $values[$key] = $answers[$key] ?? '';
    }
    // The consent flag can be changed on the member record, so it decides Yes; the form answer keeps "Please ask me first".
    $photoAnswer = $answers['photoConsent'] ?? '';
    $values['photoConsent'] = (int) $member['photo_consent'] === 1 ? 'Yes' : ($photoAnswer !== '' && strtolower($photoAnswer) !== 'yes' ? $photoAnswer : 'No');

    $values['membershipStartDate'] = hwfcMemberExportDate($member['membership_start_date']);
    $values['membershipId'] = (string) $member['membership_id'];
    $values['status'] = (string) $member['status'];
    $values['membershipExpiryDate'] = hwfcMemberExportDate($member['membership_expiry_date']);
    $values['paymentPlan'] = (string) $member['payment_frequency'];
    $values['paymentStatus'] = (string) $member['payment_status'];
    $values['lastPaymentDate'] = hwfcMemberExportDate($member['last_payment_date']);
    $values['paidThroughDate'] = hwfcMemberExportDate($member['paid_through_date']);
    $values['nextPaymentDueDate'] = hwfcMemberExportDate($member['next_payment_due_date']);
    $values['secondEmergencyRelationship'] = (string) $member['second_emergency_relationship'];
    $values['applicationReference'] = strtoupper(substr((string) $member['application_uuid'], 0, 10));

    return $values;
}

function hwfcMemberExportDate(?string $isoDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $isoDate);
    return $date === false ? (string) $isoDate : $date->format('d/m/Y');
}

/** Splits stored health information back into the form's question columns. */
function hwfcMemberExportHealth(int $healthRelevant, string $healthDetails): array
{
    $values = ['medical' => 'No', 'medicalDetails' => '', 'allergies' => 'No', 'allergyDetails' => '',
        'medication' => 'No', 'medicationDetails' => '', 'mobility' => 'No', 'otherSupport' => ''];
    if ($healthRelevant !== 1 || trim($healthDetails) === '') {
        return $values;
    }

    $labels = [
        'Medical condition' => ['medical', 'medicalDetails'],
        'Allergies' => ['allergies', 'allergyDetails'],
        'Emergency medication' => ['medication', 'medicationDetails'],
        'Mobility, balance, hearing or sight' => ['mobility', 'mobility'],
        'Other safety information' => ['otherSupport', 'otherSupport'],
    ];
    $details = [];
    $unlabelled = [];
    $current = null;
    foreach (preg_split('/\R/u', trim($healthDetails)) ?: [] as $line) {
        $parts = explode(': ', $line, 2);
        if (count($parts) === 2 && isset($labels[$parts[0]])) {
            $current = $parts[0];
            $details[$current] = $parts[1] === 'Yes (no details given)' ? '' : $parts[1];
        } elseif ($current !== null) {
            // A multi-line answer continues the previous labelled answer.
            $details[$current] .= "\n" . $line;
        } else {
            $unlabelled[] = $line;
        }
    }
    // Health information entered on the website form has no question labels; keep it under the medical question.
    if ($unlabelled) {
        $details['Medical condition'] = trim(implode("\n", $unlabelled) . "\n" . ($details['Medical condition'] ?? ''));
    }

    foreach ($details as $label => $detail) {
        [$answerKey, $detailKey] = $labels[$label];
        $values[$answerKey] = 'Yes';
        if ($detail !== '') {
            $values[$detailKey] = $detail;
        }
    }

    return $values;
}

function hwfcMemberExportDateTime(string $iso): string
{
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Europe/London'))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $iso;
    }
}

/** Stops spreadsheet apps treating a value as a formula; plain +44 / -1 style numbers are left alone. */
function hwfcMemberExportCell(string $value): string
{
    if (preg_match('/^[=@\t\r]/', $value) || (preg_match('/^[+\-]/', $value) && !preg_match('/^[+\-][\d\s()]*$/', $value))) {
        return "'" . $value;
    }
    return $value;
}

function hwfcMemberExportCsv(array $rows): string
{
    $handle = fopen('php://temp', 'w+b');
    fwrite($handle, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $csv = (string) stream_get_contents($handle);
    fclose($handle);
    return $csv;
}

/** Resolves a row carrying a Membership ID against the register. */
function hwfcMemberImportMatchMembershipId(PDO $db, array $candidate, string $membershipId, bool $updateMode, array $seenIds): array
{
    $stmt = $db->prepare('SELECT * FROM membership_applications WHERE upper(membership_id) = :membership_id LIMIT 1');
    $stmt->execute([':membership_id' => $membershipId]);
    $existing = $stmt->fetch();
    unset($candidate['update'], $candidate['changes']);

    if (isset($seenIds[$membershipId])) {
        return ['result' => 'Invalid', 'messages' => ['Repeats Membership ID ' . $membershipId . ' from spreadsheet row ' . $seenIds[$membershipId] . '.']] + $candidate;
    }
    if (!$existing) {
        return ['result' => 'Invalid', 'messages' => ['Membership ID ' . $membershipId . ' was not found. Leave Membership ID blank to add a new member.']] + $candidate;
    }
    if (!$updateMode) {
        return ['result' => 'Duplicate', 'messages' => ['Matches existing member ' . $existing['membership_id'] . '; the existing record will not be changed.']] + $candidate;
    }

    [$set, $changes, $errors, $warnings] = hwfcMemberImportUpdateChanges($existing, $candidate['values']);
    $candidate['summary']['name'] = trim(($set['first_name'] ?? $existing['first_name']) . ' ' . ($set['last_name'] ?? $existing['last_name']));
    $candidate['summary']['email'] = $set['email'] ?? $existing['email'];
    $candidate['summary']['dateOfBirth'] = $set['date_of_birth'] ?? $existing['date_of_birth'];
    $candidate['summary']['membershipStartDate'] = array_key_exists('membership_start_date', $set) ? $set['membership_start_date'] : $existing['membership_start_date'];
    $candidate['summary']['membershipId'] = $existing['membership_id'];

    if ($errors) {
        return ['result' => 'Invalid', 'messages' => $errors] + $candidate;
    }

    $candidate['result'] = $set ? 'Update' : 'Unchanged';
    $candidate['messages'] = $warnings;
    $candidate['changes'] = $changes;
    $candidate['update'] = [
        'uuid' => $existing['application_uuid'],
        'membershipId' => $existing['membership_id'],
        'updatedAt' => $existing['updated_at'],
        'set' => $set,
    ];
    return $candidate;
}

/**
 * Compares an import row with the existing member in export format. Blank cells leave values
 * unchanged. Returns [column => new value, preview changes, errors, warnings].
 */
function hwfcMemberImportUpdateChanges(array $existing, array $v): array
{
    $old = hwfcMemberExportValues($existing);
    $set = [];
    $changes = [];
    $errors = [];
    $warnings = [];
    $record = static function (string $label, string $from, string $to, array $columns) use (&$set, &$changes): void {
        $set = array_merge($set, $columns);
        $changes[] = ['field' => $label, 'from' => $from, 'to' => $to];
    };

    $fullName = hwfcMemberImportName($v['fullName']);
    if ($fullName !== '' && $fullName !== $old['fullName']) {
        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            $errors[] = 'Full name must include a first and last name.';
        } else {
            $lastName = (string) array_pop($parts);
            $record('Full name', $old['fullName'], $fullName, ['first_name' => implode(' ', $parts), 'last_name' => $lastName]);
        }
    }

    $text = [
        'preferredName' => ['Preferred name', 'preferred_name'],
        'address' => ['Home address', 'address'],
        'postcode' => ['Postcode', 'postcode'],
        'phone' => ['Telephone number', 'phone'],
        'email' => ['Email address', 'email'],
        'emergencyName' => ['Emergency contact name', 'emergency_name'],
        'emergencyRelationship' => ['Emergency contact relationship', 'emergency_relationship'],
        'emergencyPhone' => ['Emergency contact telephone number', 'emergency_phone'],
        'secondEmergencyName' => ['Second emergency contact name', 'second_emergency_name'],
        'secondEmergencyPhone' => ['Second emergency contact telephone number', 'second_emergency_phone'],
        'secondEmergencyRelationship' => ['Second emergency contact relationship', 'second_emergency_relationship'],
    ];
    foreach ($text as $key => [$label, $column]) {
        $value = match ($key) {
            'preferredName', 'emergencyName', 'secondEmergencyName' => hwfcMemberImportName($v[$key]),
            'postcode' => strtoupper($v[$key]),
            'email' => strtolower(preg_replace('/\s+/u', '', $v[$key]) ?? $v[$key]),
            default => $v[$key],
        };
        if ($value === '' || $value === $old[$key]) {
            continue;
        }
        if ($key === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
            continue;
        }
        $record($label, $old[$key], $value, [$column => $value]);
    }

    if ($v['dateOfBirth'] !== '' && $v['dateOfBirth'] !== $old['dateOfBirth']) {
        $dateOfBirth = hwfcMemberImportDate($v['dateOfBirth']);
        if ($dateOfBirth === null) {
            $errors[] = 'Date of birth is invalid.';
        } elseif ($dateOfBirth > (new DateTimeImmutable('today'))->modify('-16 years')->format('Y-m-d')) {
            $errors[] = 'Date of birth looks wrong (the member would be under 16).';
        } elseif ($dateOfBirth !== $existing['date_of_birth']) {
            $record('Date of birth', $old['dateOfBirth'], hwfcMemberExportDate($dateOfBirth), ['date_of_birth' => $dateOfBirth]);
        }
    }

    if ($v['preferredContact'] !== '' && $v['preferredContact'] !== $old['preferredContact']) {
        $contact = match (strtolower($v['preferredContact'])) {
            'text message', 'text' => 'text',
            'whatsapp' => 'whatsapp',
            'phone call', 'phone', 'telephone' => 'phone',
            'email' => 'email',
            default => null,
        };
        if ($contact === null) {
            $errors[] = 'Preferred method of contact is not recognised.';
        } elseif ($contact !== $existing['preferred_contact']) {
            $record('Preferred method of contact', $old['preferredContact'], $v['preferredContact'], ['preferred_contact' => $contact]);
        }
    }

    // Form answers are rewritten as a block; the photo answer also sets the consent flag.
    $answers = hwfcFormAnswersParse($existing['form_answers'] ?? null);
    $answersChanged = false;
    foreach (HWFC_MEMBER_FORM_ANSWER_LABELS as $key => $label) {
        if ($v[$key] === '' || $v[$key] === $old[$key]) {
            continue;
        }
        $columns = [];
        if ($key === 'photoConsent') {
            $consent = strtolower($v[$key]) === 'yes' ? 1 : 0;
            if ($consent !== (int) $existing['photo_consent']) {
                $columns['photo_consent'] = $consent;
            }
        }
        $answers[$key] = $v[$key];
        $answersChanged = true;
        $record($label, $old[$key], $v[$key], $columns);
    }
    if ($answersChanged) {
        $set['form_answers'] = hwfcFormAnswersBuild($answers);
    }

    // Health columns are compared as a group; the preview never shows their content.
    $healthKeys = ['medical', 'medicalDetails', 'allergies', 'allergyDetails', 'medication', 'medicationDetails', 'mobility', 'otherSupport'];
    $rowHealth = array_intersect_key($v, array_flip($healthKeys));
    if (implode('', $rowHealth) !== '' && $rowHealth != array_intersect_key($old, array_flip($healthKeys))) {
        $lines = array_values(array_filter([
            hwfcMemberImportHealthLine('Medical condition', $v['medical'], $v['medicalDetails']),
            hwfcMemberImportHealthLine('Allergies', $v['allergies'], $v['allergyDetails']),
            hwfcMemberImportHealthLine('Emergency medication', $v['medication'], $v['medicationDetails']),
            hwfcMemberImportHealthLine('Mobility, balance, hearing or sight', $v['mobility'], ''),
            hwfcMemberImportHealthLine('Other safety information', $v['otherSupport'], ''),
        ]));
        $details = $lines ? implode("\n", $lines) : null;
        if ($details !== ($existing['health_details'] ?: null)) {
            $record('Health information', 'Previous', 'Changed', ['health_relevant' => $lines ? 1 : 0, 'health_details' => $details]);
        }
    }

    // Membership dates: a changed start date with an unchanged expiry moves the expiry to one year less a day.
    $start = $existing['membership_start_date'];
    $expiry = $existing['membership_expiry_date'];
    $datesChanged = false;
    if ($v['membershipStartDate'] !== '' && $v['membershipStartDate'] !== $old['membershipStartDate']) {
        $parsed = hwfcMemberImportDate($v['membershipStartDate'], true);
        if ($parsed === null) {
            $errors[] = 'Membership start date is invalid.';
        } elseif ($parsed !== $start) {
            $start = $parsed;
            $datesChanged = true;
            $expiry = hwfcMemberAnnualExpiry($start);
        }
    }
    if ($v['membershipExpiryDate'] !== '' && $v['membershipExpiryDate'] !== $old['membershipExpiryDate']) {
        $parsed = hwfcMemberImportDate($v['membershipExpiryDate'], true);
        if ($parsed === null) {
            $errors[] = 'Membership expiry date is invalid.';
        } else {
            $expiry = $parsed;
            $datesChanged = $datesChanged || $parsed !== $existing['membership_expiry_date'];
        }
    }
    if ($datesChanged) {
        if ($start === null || $expiry < $start) {
            $errors[] = 'Membership expiry date cannot be before the start date.';
        } else {
            if ($start !== $existing['membership_start_date']) {
                $record('Membership start date', $old['membershipStartDate'], hwfcMemberExportDate($start), ['membership_start_date' => $start]);
            }
            if ($expiry !== $existing['membership_expiry_date']) {
                $record('Membership expiry date', $old['membershipExpiryDate'], hwfcMemberExportDate($expiry), ['membership_expiry_date' => $expiry]);
            }
        }
    }

    // Payment details follow the Date Details rules, which apply only to active members.
    $payment = [];
    foreach (['paymentPlan' => HWFC_MEMBER_PAYMENT_PLANS, 'paymentStatus' => HWFC_MEMBER_PAYMENT_STATUSES] as $key => $allowed) {
        if ($v[$key] === '' || $v[$key] === $old[$key]) {
            continue;
        }
        $match = array_values(array_filter($allowed, static fn(string $option): bool => strcasecmp($option, $v[$key]) === 0));
        if (!$match) {
            $errors[] = HWFC_MEMBER_RESPONSES_HEADINGS[$key] . ' must be one of: ' . implode(', ', $allowed) . '.';
        } elseif ($match[0] !== $old[$key]) {
            $payment[$key] = $match[0];
        }
    }
    foreach (['lastPaymentDate', 'paidThroughDate'] as $key) {
        if ($v[$key] === '' || $v[$key] === $old[$key]) {
            continue;
        }
        $parsed = hwfcMemberImportDate($v[$key], true);
        if ($parsed === null) {
            $errors[] = HWFC_MEMBER_RESPONSES_HEADINGS[$key] . ' is invalid.';
        } elseif (hwfcMemberExportDate($parsed) !== $old[$key]) {
            $payment[$key] = $parsed;
        }
    }
    if ($payment && $existing['status'] !== 'Active') {
        $errors[] = 'Payment details can only be changed for an active member.';
    } elseif (isset($payment['paidThroughDate']) && $start !== null && $payment['paidThroughDate'] < $start) {
        $errors[] = 'Paid through date cannot be before the membership start date.';
    } else {
        $columns = ['paymentPlan' => 'payment_frequency', 'paymentStatus' => 'payment_status', 'lastPaymentDate' => 'last_payment_date', 'paidThroughDate' => 'paid_through_date'];
        foreach ($payment as $key => $value) {
            $isDate = str_ends_with($key, 'Date');
            $update = [$columns[$key] => $value];
            if ($key === 'paidThroughDate') {
                $update['next_payment_due_date'] = (new DateTimeImmutable($value))->modify('+1 day')->format('Y-m-d');
            }
            $record(HWFC_MEMBER_RESPONSES_HEADINGS[$key], $old[$key], $isDate ? hwfcMemberExportDate($value) : $value, $update);
        }
    }

    if ($v['status'] !== '' && strcasecmp($v['status'], (string) $existing['status']) !== 0) {
        $warnings[] = 'Status is not changed by import; use the member record to accept, reject or undo.';
    }

    return [$set, $changes, $errors, $warnings];
}

function hwfcMemberImportApplyUpdate(PDO $db, array $candidate, string $fileName, string $actor): void
{
    $set = $candidate['update']['set'];
    $assignments = implode(', ', array_map(static fn(string $column): string => $column . ' = :' . $column, array_keys($set)));
    $stmt = $db->prepare('UPDATE membership_applications SET ' . $assignments . ', updated_at = :updated_at WHERE application_uuid = :uuid');
    $params = [':updated_at' => gmdate('c'), ':uuid' => $candidate['update']['uuid']];
    foreach ($set as $column => $value) {
        $params[':' . $column] = $value;
    }
    $stmt->execute($params);

    // Field names only: the audit trail does not copy personal data.
    hwfcAudit($db, $candidate['update']['uuid'], 'member_import_updated', [
        'membershipId' => $candidate['update']['membershipId'],
        'changedFields' => array_column($candidate['changes'], 'field'),
        'fileName' => $fileName,
        'spreadsheetRow' => $candidate['rowNumber'],
        'actor' => $actor,
    ]);
}
