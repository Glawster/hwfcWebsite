<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../members/lib/memberDb.php';

$dbPath = (string) (getenv('HWFC_MEMBER_DB') ?: '');
$allowExplicitly = getenv('HWFC_ALLOW_TEST_DATA') === '1';
$isClearlyNonProduction = $dbPath !== '' && preg_match('/(?:staging|test|dev)/i', $dbPath) === 1;

if (!$isClearlyNonProduction && !$allowExplicitly) {
    fwrite(STDERR, "Refusing to seed this database. HWFC_MEMBER_DB must contain staging, test or dev.\n");
    fwrite(STDERR, "For an intentional local database, set HWFC_ALLOW_TEST_DATA=1.\n");
    exit(1);
}

$resetOnly = in_array('--reset', $argv, true);
$testUuids = [
    '10000000000000000000000000000001',
    '10000000000000000000000000000002',
    '10000000000000000000000000000003',
    '10000000000000000000000000000004',
    '10000000000000000000000000000005',
    '10000000000000000000000000000006',
];

$db = hwfcMemberDb();
$db->beginTransaction();

try {
    $placeholders = implode(',', array_fill(0, count($testUuids), '?'));

    $deletePayments = $db->prepare("DELETE FROM membership_payments WHERE application_uuid IN ($placeholders)");
    $deletePayments->execute($testUuids);

    $deleteAudit = $db->prepare("DELETE FROM membership_audit WHERE application_uuid IN ($placeholders)");
    $deleteAudit->execute($testUuids);

    $deleteMembers = $db->prepare("DELETE FROM membership_applications WHERE application_uuid IN ($placeholders)");
    $deleteMembers->execute($testUuids);

    $db->exec("DELETE FROM session_takings WHERE attendance_source = 'test'");

    if ($resetOnly) {
        $db->commit();
        echo "HWFC test data removed from {$dbPath}.\n";
        exit(0);
    }

    $members = [
        [
            'uuid' => $testUuids[0],
            'membershipId' => null,
            'firstName' => 'Peter',
            'lastName' => 'Pending',
            'preferredName' => 'Pete',
            'email' => 'peter.pending@example.invalid',
            'status' => 'Pending',
            'startDate' => null,
            'expiryDate' => null,
            'paymentFrequency' => 'Not Set',
            'paymentStatus' => 'Not Recorded',
            'lastPaymentDate' => null,
            'paidThroughDate' => null,
            'nextPaymentDueDate' => null,
        ],
        [
            'uuid' => $testUuids[1],
            'membershipId' => 'HWFC-000901',
            'firstName' => 'Alice',
            'lastName' => 'Annual',
            'preferredName' => null,
            'email' => 'alice.annual@example.invalid',
            'status' => 'Active',
            'startDate' => '2026-10-01',
            'expiryDate' => '2027-09-30',
            'paymentFrequency' => 'Annual',
            'paymentStatus' => 'Paid In Full',
            'lastPaymentDate' => '2026-10-01',
            'paidThroughDate' => '2027-09-30',
            'nextPaymentDueDate' => null,
        ],
        [
            'uuid' => $testUuids[2],
            'membershipId' => 'HWFC-000902',
            'firstName' => 'Michael',
            'lastName' => 'Monthly',
            'preferredName' => 'Mike',
            'email' => 'michael.monthly@example.invalid',
            'status' => 'Active',
            'startDate' => '2026-08-15',
            'expiryDate' => '2027-08-14',
            'paymentFrequency' => 'Monthly',
            'paymentStatus' => 'Current',
            'lastPaymentDate' => '2026-10-15',
            'paidThroughDate' => '2026-11-14',
            'nextPaymentDueDate' => '2026-11-15',
        ],
        [
            'uuid' => $testUuids[3],
            'membershipId' => 'HWFC-000903',
            'firstName' => 'Olivia',
            'lastName' => 'Overdue',
            'preferredName' => null,
            'email' => 'olivia.overdue@example.invalid',
            'status' => 'Active',
            'startDate' => '2026-07-01',
            'expiryDate' => '2027-06-30',
            'paymentFrequency' => 'Monthly',
            'paymentStatus' => 'Overdue',
            'lastPaymentDate' => '2026-08-01',
            'paidThroughDate' => '2026-08-31',
            'nextPaymentDueDate' => '2026-09-01',
        ],
        [
            'uuid' => $testUuids[4],
            'membershipId' => null,
            'firstName' => 'Rachel',
            'lastName' => 'Rejected',
            'preferredName' => null,
            'email' => 'rachel.rejected@example.invalid',
            'status' => 'Rejected',
            'startDate' => null,
            'expiryDate' => null,
            'paymentFrequency' => 'Not Set',
            'paymentStatus' => 'Not Recorded',
            'lastPaymentDate' => null,
            'paidThroughDate' => null,
            'nextPaymentDueDate' => null,
        ],
        [
            'uuid' => $testUuids[5],
            'membershipId' => 'HWFC-000904',
            'firstName' => 'Larry',
            'lastName' => 'Lapsed',
            'preferredName' => null,
            'email' => 'larry.lapsed@example.invalid',
            'status' => 'Lapsed',
            'startDate' => '2025-09-01',
            'expiryDate' => '2026-08-31',
            'paymentFrequency' => 'Annual',
            'paymentStatus' => 'Paid In Full',
            'lastPaymentDate' => '2025-09-01',
            'paidThroughDate' => '2026-08-31',
            'nextPaymentDueDate' => null,
        ],
    ];

    $insertMember = $db->prepare(<<<'SQL'
INSERT INTO membership_applications (
    application_uuid, membership_id, first_name, last_name, preferred_name,
    date_of_birth, address, postcode, email, phone, preferred_contact,
    emergency_name, emergency_relationship, emergency_phone,
    second_emergency_name, second_emergency_relationship, second_emergency_phone,
    health_relevant, health_details, photo_consent, privacy_version, admin_notes,
    membership_start_date, membership_expiry_date, payment_frequency, payment_status,
    last_payment_date, paid_through_date, next_payment_due_date,
    submitted_at, updated_at, status
) VALUES (
    :uuid, :membershipId, :firstName, :lastName, :preferredName,
    '1960-01-01', '1 Test Street\nLisburn', 'BT28 1AA', :email, '07000 000000', 'email',
    'Test Emergency Contact', 'Friend', '07000 000001',
    NULL, NULL, NULL,
    0, NULL, 1, '2026-10-27', 'TEST DATA - safe to delete',
    :startDate, :expiryDate, :paymentFrequency, :paymentStatus,
    :lastPaymentDate, :paidThroughDate, :nextPaymentDueDate,
    :submittedAt, :updatedAt, :status
)
SQL);

    foreach ($members as $index => $member) {
        $submittedAt = sprintf('2026-10-%02dT12:00:00Z', 10 + $index);
        $insertMember->execute([
            ':uuid' => $member['uuid'],
            ':membershipId' => $member['membershipId'],
            ':firstName' => $member['firstName'],
            ':lastName' => $member['lastName'],
            ':preferredName' => $member['preferredName'],
            ':email' => $member['email'],
            ':startDate' => $member['startDate'],
            ':expiryDate' => $member['expiryDate'],
            ':paymentFrequency' => $member['paymentFrequency'],
            ':paymentStatus' => $member['paymentStatus'],
            ':lastPaymentDate' => $member['lastPaymentDate'],
            ':paidThroughDate' => $member['paidThroughDate'],
            ':nextPaymentDueDate' => $member['nextPaymentDueDate'],
            ':submittedAt' => $submittedAt,
            ':updatedAt' => $submittedAt,
            ':status' => $member['status'],
        ]);
        hwfcAudit($db, $member['uuid'], 'test_data_seeded', ['actor' => 'seedTestData']);
    }

    $insertPayment = $db->prepare(<<<'SQL'
INSERT INTO membership_payments (
    application_uuid, membership_id, payment_date, amount_pence,
    payment_type, paid_through_date, recorded_at
) VALUES (
    :uuid, :membershipId, :paymentDate, :amountPence,
    :paymentType, :paidThroughDate, :recordedAt
)
SQL);

    $payments = [
        [$testUuids[1], 'HWFC-000901', '2026-10-01', 6000, 'annual', '2027-09-30'],
        [$testUuids[2], 'HWFC-000902', '2026-08-15', 500, 'monthly', '2026-09-14'],
        [$testUuids[2], 'HWFC-000902', '2026-09-15', 500, 'monthly', '2026-10-14'],
        [$testUuids[2], 'HWFC-000902', '2026-10-15', 500, 'monthly', '2026-11-14'],
        [$testUuids[3], 'HWFC-000903', '2026-07-01', 500, 'monthly', '2026-07-31'],
        [$testUuids[3], 'HWFC-000903', '2026-08-01', 500, 'monthly', '2026-08-31'],
        [$testUuids[5], 'HWFC-000904', '2025-09-01', 6000, 'annual', '2026-08-31'],
    ];

    foreach ($payments as [$uuid, $membershipId, $paymentDate, $amountPence, $paymentType, $paidThroughDate]) {
        $insertPayment->execute([
            ':uuid' => $uuid,
            ':membershipId' => $membershipId,
            ':paymentDate' => $paymentDate,
            ':amountPence' => $amountPence,
            ':paymentType' => $paymentType,
            ':paidThroughDate' => $paidThroughDate,
            ':recordedAt' => $paymentDate . 'T12:00:00Z',
        ]);
    }

    $insertTakings = $db->prepare(<<<'SQL'
INSERT INTO session_takings (
    session_date, session_name, member_count, visitor_count,
    member_rate_pence, visitor_rate_pence, total_pence,
    collected_by, notes, recorded_at, attendance_source,
    venue, expected_total_pence, actual_total_pence
) VALUES (
    :sessionDate, :sessionName, :memberCount, 0,
    200, 0, :actualPence,
    'Test Collector', 'TEST DATA - safe to delete', :recordedAt, 'test',
    :venue, :expectedPence, :actualPence
)
SQL);

    $sessions = [
        ['2026-10-20', 'Tuesday', 14, 'LLC', 2800, 2800],
        ['2026-10-21', 'Wednesday', 10, 'Football Factory', 2000, 2000],
        ['2026-10-22', 'Thursday', 13, 'LLC', 2600, 2400],
        ['2026-10-23', 'Friday', 8, 'Football Factory', 1600, 1600],
        ['2026-10-25', 'Sunday', 11, 'Football Factory', 2200, 2200],
    ];

    foreach ($sessions as [$sessionDate, $sessionName, $memberCount, $venue, $expectedPence, $actualPence]) {
        $insertTakings->execute([
            ':sessionDate' => $sessionDate,
            ':sessionName' => $sessionName,
            ':memberCount' => $memberCount,
            ':actualPence' => $actualPence,
            ':recordedAt' => $sessionDate . 'T12:00:00Z',
            ':venue' => $venue,
            ':expectedPence' => $expectedPence,
        ]);
    }

    $db->commit();
    echo "HWFC test data seeded into {$dbPath}.\n";
    echo "Members: 6 (Pending, Active annual, Active monthly, Overdue, Rejected, Lapsed).\n";
    echo "Membership payments: 7. Session takings: 5.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
