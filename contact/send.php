<?php

declare(strict_types=1);

const RECIPIENT = 'info@hillsboroughwalkingfootball.com';
const MAX_MESSAGE_LENGTH = 4000;

function redirectWithStatus(string $status): never
{
    header('Location: /contact/?' . $status . '=1', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithStatus('error');
}

if (!empty($_POST['website'] ?? '')) {
    redirectWithStatus('sent');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$topic = trim((string) ($_POST['topic'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$topics = [
    'try' => "I'd like to try walking football",
    'mens' => "Men's sessions",
    'ladies' => "Ladies' sessions",
    'fixtures' => 'Fixtures / visiting club',
    'general' => 'General enquiry',
];

if (
    $name === '' ||
    $email === '' ||
    $message === '' ||
    !isset($topics[$topic]) ||
    filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
    mb_strlen($name) > 120 ||
    mb_strlen($email) > 200 ||
    mb_strlen($phone) > 50 ||
    mb_strlen($message) > MAX_MESSAGE_LENGTH
) {
    redirectWithStatus('error');
}

$cleanName = str_replace(["\r", "\n"], ' ', $name);
$cleanEmail = str_replace(["\r", "\n"], '', $email);
$cleanPhone = str_replace(["\r", "\n"], ' ', $phone);
$subject = 'HWFC website enquiry: ' . $topics[$topic];

$body = "A new enquiry was submitted through the HWFC website.\n\n";
$body .= "Name: {$cleanName}\n";
$body .= "Email: {$cleanEmail}\n";
$body .= "Phone: " . ($cleanPhone !== '' ? $cleanPhone : 'Not provided') . "\n";
$body .= "Enquiry type: {$topics[$topic]}\n\n";
$body .= "Message:\n{$message}\n";

$host = $_SERVER['HTTP_HOST'] ?? 'hillsboroughwalkingfootball.com';
$host = preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'hillsboroughwalkingfootball.com';
$headers = [
    'From: HWFC Website <info@' . $host . '>',
    'Reply-To: ' . $cleanName . ' <' . $cleanEmail . '>',
    'Content-Type: text/plain; charset=UTF-8',
];

$sent = mail(RECIPIENT, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    redirectWithStatus('error');
}

redirectWithStatus('sent');
