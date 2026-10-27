<?php

declare(strict_types=1);

function hwfcClean(?string $value): string
{
    return trim((string) $value);
}

function hwfcValidateApplication(array $input): array
{
    $errors = [];
    $required = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'date_of_birth' => 'Date of birth',
        'address' => 'Address',
        'postcode' => 'Postcode',
        'email' => 'Email address',
        'phone' => 'Telephone number',
        'preferred_contact' => 'Preferred contact method',
        'membership_section' => 'Membership section',
        'emergency_name' => 'Emergency contact name',
        'emergency_relationship' => 'Emergency contact relationship',
        'emergency_phone' => 'Emergency contact telephone number',
    ];

    foreach ($required as $key => $label) {
        if (hwfcClean($input[$key] ?? '') === '') {
            $errors[$key] = $label . ' is required.';
        }
    }

    if (($input['email'] ?? '') !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    $dob = hwfcClean($input['date_of_birth'] ?? '');
    if ($dob !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTimeImmutable('today')) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        }
    }

    if (!in_array($input['preferred_contact'] ?? '', ['phone', 'text', 'whatsapp', 'email'], true)) {
        $errors['preferred_contact'] = 'Choose a preferred contact method.';
    }

    if (!in_array($input['membership_section'] ?? '', ['mens', 'ladies', 'unsure'], true)) {
        $errors['membership_section'] = 'Choose the membership section that applies.';
    }

    if (!in_array($input['health_relevant'] ?? '', ['yes', 'no'], true)) {
        $errors['health_relevant'] = 'Please tell us whether you have relevant health information.';
    }
    if (($input['health_relevant'] ?? '') === 'yes' && hwfcClean($input['health_details'] ?? '') === '') {
        $errors['health_details'] = 'Please provide the relevant health, medication or participation information.';
    }

    if (!in_array($input['photo_consent'] ?? '', ['yes', 'no'], true)) {
        $errors['photo_consent'] = 'Please choose Yes or No for photography/publicity.';
    }

    if (($input['accuracy_confirmed'] ?? '') !== 'yes') {
        $errors['accuracy_confirmed'] = 'Please confirm that the information is accurate.';
    }

    return $errors;
}
