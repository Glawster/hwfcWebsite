<?php

declare(strict_types=1);

require_once __DIR__ . '/../../kit/lib/kitConfig.php';

function hwfcMembershipMailMode(): string
{
    $mode = strtolower(trim((string) (getenv('HWFC_MAIL_MODE') ?: 'log')));
    if (!in_array($mode, ['log', 'send'], true)) {
        throw new RuntimeException('HWFC_MAIL_MODE must be either log or send.');
    }
    return $mode;
}

function hwfcMembershipMailLogPath(): string
{
    $configured = trim((string) (getenv('HWFC_MAIL_LOG') ?: ''));
    if ($configured !== '') {
        return $configured;
    }

    $dbPath = getenv('HWFC_MEMBER_DB');
    if (is_string($dbPath) && trim($dbPath) !== '') {
        return dirname($dbPath) . DIRECTORY_SEPARATOR . 'membership-mail.log';
    }

    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hwfc-membership-mail.log';
}

function hwfcMembershipAcceptanceMessage(array $member, array $config): array
{
    $email = trim((string) ($member['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        throw new RuntimeException('The member does not have a valid email address.');
    }

    $membershipId = trim((string) ($member['membershipId'] ?? ''));
    $firstName = trim((string) (($member['preferredName'] ?? '') ?: ($member['firstName'] ?? '')));
    $startDate = trim((string) ($member['membershipStartDate'] ?? ''));
    $expiryDate = trim((string) ($member['membershipExpiryDate'] ?? ''));
    $frequency = trim((string) ($member['paymentFrequency'] ?? ''));

    if ($membershipId === '' || $startDate === '' || $expiryDate === '') {
        throw new RuntimeException('Set the Membership ID and membership dates before sending the acceptance email.');
    }
    if (!in_array($frequency, ['Annual', 'Monthly'], true)) {
        throw new RuntimeException('Choose Annual or Monthly payment frequency before sending the acceptance email.');
    }

    $bank = $config['bank'] ?? [];
    foreach (['accountName', 'sortCode', 'accountNumber'] as $key) {
        if (!is_string($bank[$key] ?? null) || trim($bank[$key]) === '') {
            throw new RuntimeException('Bank-transfer configuration is incomplete.');
        }
    }

    $contactEmail = trim((string) ($config['managerEmail'] ?? 'info@hillsboroughwalkingfootball.com'));
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $contactEmail = 'info@hillsboroughwalkingfootball.com';
    }

    $subject = 'HWFC Membership Accepted — ' . $membershipId;
    $body = implode("\n", [
        'Hello ' . ($firstName !== '' ? $firstName : 'there') . ',',
        '',
        'Your application to join Hillsborough Walking Football Club has been accepted.',
        '',
        'Membership ID: ' . $membershipId,
        'Membership period: ' . $startDate . ' to ' . $expiryDate,
        'Payment plan: ' . $frequency,
        '',
        'Membership costs £60 per year, or £5 per month if paying monthly.',
        '',
        'Please pay by bank transfer using the following details:',
        'Account name: ' . trim((string) $bank['accountName']),
        'Sort code: ' . trim((string) $bank['sortCode']),
        'Account number: ' . trim((string) $bank['accountNumber']),
        'Payment reference: ' . $membershipId,
        '',
        'Please use your Membership ID as the payment reference so we can match the payment to your membership.',
        'Your payment status will be updated after the club confirms the bank transfer.',
        '',
        'If you have any questions, contact ' . $contactEmail . '.',
        '',
        'Hillsborough Walking Football Club',
        'https://hillsboroughwalkingfootball.com',
    ]);

    return [
        'to' => $email,
        'subject' => $subject,
        'body' => $body,
        'from' => trim((string) $config['fromEmail']),
    ];
}

function hwfcMembershipSendAcceptance(array $member): string
{
    $config = kitLoadConfig();
    $message = hwfcMembershipAcceptanceMessage($member, $config);
    $mode = hwfcMembershipMailMode();

    if ($mode === 'log') {
        $path = hwfcMembershipMailLogPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create the membership mail log directory.');
        }

        $entry = implode("\n", [
            '--- ' . gmdate('c') . ' ---',
            'To: ' . $message['to'],
            'From: ' . $message['from'],
            'Subject: ' . $message['subject'],
            '',
            $message['body'],
            '',
        ]);
        if (file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the membership mail log.');
        }
        @chmod($path, 0600);
        return 'logged';
    }

    $headers = [
        'From: Hillsborough Walking Football Club <' . $message['from'] . '>',
        'Reply-To: ' . $message['from'],
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if (!mail($message['to'], $message['subject'], $message['body'], implode("\r\n", $headers))) {
        throw new RuntimeException('The membership acceptance email could not be sent.');
    }

    return 'sent';
}
