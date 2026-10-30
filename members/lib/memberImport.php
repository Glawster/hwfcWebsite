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
];

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
function hwfcMemberImportCandidates(array $rows): array
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
    if ($missing) {
        throw new RuntimeException('The spreadsheet does not match the member responses template. Missing columns: ' . implode('; ', $missing));
    }

    $candidates = [];
    foreach ($rows as $rowNumber => $row) {
        if ($rowNumber === $headerRowNumber || implode('', $row) === '') {
            continue;
        }
        $values = [];
        foreach ($columns as $key => $index) {
            $values[$key] = trim((string) ($row[$index] ?? ''));
        }
        $candidates[] = hwfcMemberImportCandidate((int) $rowNumber, $values);
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

function hwfcMemberImportCandidate(int $rowNumber, array $v): array
{
    $errors = [];

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
    $v['email'] = preg_replace('/\s+/u', '', $v['email']) ?? $v['email'];

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
        $v['membershipType'] !== '' ? 'Membership type: ' . $v['membershipType'] : null,
        $v['clubNews'] !== '' ? 'Club news and updates: ' . $v['clubNews'] : null,
        $v['whatsApp'] !== '' ? 'Club WhatsApp group: ' . $v['whatsApp'] : null,
        $v['photoConsent'] !== '' ? 'Photographs or videos answer: ' . $v['photoConsent'] : null,
        $v['helping'] !== '' ? 'Helping with club activities: ' . $v['helping'] : null,
    ]);

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
        ],
        'record' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'preferred_name' => $v['preferredName'] !== '' ? $v['preferredName'] : null,
            'date_of_birth' => $dateOfBirth,
            'address' => $v['address'],
            'postcode' => strtoupper($v['postcode']),
            'email' => strtolower($v['email']),
            'phone' => $v['phone'],
            'preferred_contact' => $preferredContact,
            'emergency_name' => $v['emergencyName'],
            'emergency_relationship' => $v['emergencyRelationship'],
            'emergency_phone' => $v['emergencyPhone'],
            'second_emergency_name' => $v['secondEmergencyName'] !== '' ? $v['secondEmergencyName'] : null,
            'second_emergency_phone' => $v['secondEmergencyPhone'] !== '' ? $v['secondEmergencyPhone'] : null,
            'health_relevant' => $healthLines ? 1 : 0,
            'health_details' => $healthLines ? implode("\n", $healthLines) : null,
            'photo_consent' => strtolower($v['photoConsent']) === 'yes' ? 1 : 0,
            'admin_notes' => implode("\n", $notes),
            'submitted_at' => $submittedAt,
        ],
        'privacyAcknowledged' => strtolower($v['privacyAcknowledged']) === 'i confirm',
    ];
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
function hwfcMemberImportDate(string $value): ?string
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
    if ($date === false || $date > new DateTimeImmutable('today') || (int) $date->format('Y') < 1900) {
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

/** Marks candidates that match an existing member or an earlier row in the same file. */
function hwfcMemberImportMarkDuplicates(PDO $db, array $candidates): array
{
    $seen = [];
    foreach ($candidates as &$candidate) {
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

/** Inserts New candidates as Pending applications in one transaction. Returns [imported, skipped]. */
function hwfcMemberImportCommit(PDO $db, array $candidates, string $fileName, string $actor): array
{
    $insert = $db->prepare(<<<'SQL'
INSERT INTO membership_applications (
    application_uuid, first_name, last_name, preferred_name, date_of_birth,
    address, postcode, email, phone, preferred_contact,
    emergency_name, emergency_relationship, emergency_phone,
    second_emergency_name, second_emergency_phone,
    health_relevant, health_details, photo_consent,
    privacy_version, admin_notes, submitted_at, updated_at, status
) VALUES (
    :uuid, :first_name, :last_name, :preferred_name, :date_of_birth,
    :address, :postcode, :email, :phone, :preferred_contact,
    :emergency_name, :emergency_relationship, :emergency_phone,
    :second_emergency_name, :second_emergency_phone,
    :health_relevant, :health_details, :photo_consent,
    :privacy_version, :admin_notes, :submitted_at, :updated_at, 'Pending'
)
SQL);

    $imported = 0;
    $skipped = 0;
    $db->beginTransaction();
    try {
        // Re-check against the database inside the transaction so a stale preview cannot create duplicates.
        foreach (hwfcMemberImportMarkDuplicates($db, $candidates) as $candidate) {
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

    return [$imported, $skipped];
}

/**
 * Builds member responses rows (heading row first) for every non-rejected member, in the
 * Google Form layout so the file can be edited offline and read by the importer.
 */
function hwfcMemberExportRows(PDO $db): array
{
    $members = $db->query(<<<'SQL'
SELECT first_name, last_name, preferred_name, date_of_birth, address, postcode, email, phone,
       preferred_contact, emergency_name, emergency_relationship, emergency_phone,
       second_emergency_name, second_emergency_phone, health_relevant, health_details,
       photo_consent, admin_notes, submitted_at
FROM membership_applications
WHERE status <> 'Rejected'
ORDER BY submitted_at, id
SQL)->fetchAll();

    $rows = [array_values(HWFC_MEMBER_RESPONSES_HEADINGS)];
    foreach ($members as $member) {
        $values = array_fill_keys(array_keys(HWFC_MEMBER_RESPONSES_HEADINGS), '');
        $values['timestamp'] = hwfcMemberExportDateTime((string) $member['submitted_at']);
        $values['fullName'] = trim($member['first_name'] . ' ' . $member['last_name']);
        $values['preferredName'] = (string) $member['preferred_name'];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $member['date_of_birth']);
        $values['dateOfBirth'] = $date === false ? (string) $member['date_of_birth'] : $date->format('d/m/Y');
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

        $notes = (string) $member['admin_notes'];
        $values['membershipType'] = hwfcMemberExportNote($notes, 'Membership type');
        $values['clubNews'] = hwfcMemberExportNote($notes, 'Club news and updates');
        $values['whatsApp'] = hwfcMemberExportNote($notes, 'Club WhatsApp group');
        $values['helping'] = hwfcMemberExportNote($notes, 'Helping with club activities');
        $photoAnswer = hwfcMemberExportNote($notes, 'Photographs or videos answer');
        $values['photoConsent'] = (int) $member['photo_consent'] === 1 ? 'Yes' : ($photoAnswer !== '' && strtolower($photoAnswer) !== 'yes' ? $photoAnswer : 'No');

        $rows[] = array_map('hwfcMemberExportCell', array_values($values));
    }

    return $rows;
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

function hwfcMemberExportNote(string $notes, string $label): string
{
    return preg_match('/^' . preg_quote($label, '/') . ': (.*)$/mu', $notes, $match) ? trim($match[1]) : '';
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
