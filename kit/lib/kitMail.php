<?php
declare(strict_types=1);

function kitOrderLinesSummary(array $o): string
{
    $text = '';
    $hasFreeShirt = false;
    foreach ($o['lines'] as $line) {
        $text .= "{$line['name']} | Size: {$line['size']} | Quantity: {$line['quantity']}";
        if ($line['initials'] !== '') $text .= " | Supplier-Applied Initials: {$line['initials']}";
        $text .= ' | Unit Base Price: ' . kitMoney($line['unitPence']);
        if ($line['initialsPence'] > 0) $text .= ' | Initials Per Unit: ' . kitMoney($line['initialsPence']);
        $text .= ' | Line Total: ' . kitMoney($line['totalPence']) . "\n";
        if ($line['freeUnits']) $hasFreeShirt = true;
    }
    if ($hasFreeShirt) {
        $text .= "\nNew Member Free Shirt – £0.00: one base shirt unit on this line. Additional units and initials remain payable.\n";
    }
    return $text;
}

function kitManagerSummary(array $o): string
{
    $m = $o['member'];
    $text = "Batch: {$o['batchId']} ({$o['batchName']})\nOrder Reference: {$o['reference']}\nSubmitted: " . kitDate($o['submittedAt']) . "\nStatus: Payment Pending\n\n";
    $text .= "Name: {$m['name']}\nEmail: {$m['email']}\nTelephone: {$m['phone']}\nSection: {$m['section']}\nAccount Holder (if different): {$m['accountHolder']}\n\n";
    $text .= 'New-Member Free Shirt Claimed: ' . ($o['claimFree'] ? 'YES — kit manager must check eligibility and previous use manually.' : 'No') . "\n\n";
    $text .= kitOrderLinesSummary($o);
    $text .= "\nFulfilment: {$o['fulfilment']}\n";
    foreach ($o['address'] as $key => $value) $text .= "$key: $value\n";
    $text .= 'Delivery Charge: ' . kitMoney($o['deliveryPence']) . "\n";
    $text .= "\nAmount Due: " . kitMoney($o['totalPence']) . "\nNotes: {$m['notes']}\n";
    return $text;
}

function kitMemberSummary(array $o): string
{
    $m = $o['member'];
    $text = "Order Reference: {$o['reference']}\nSubmitted: " . kitDate($o['submittedAt']) . "\nStatus: Payment Pending\n\n";
    $text .= "Name: {$m['name']}\nEmail: {$m['email']}\nTelephone: {$m['phone']}\nSection: {$m['section']}\nAccount Holder (if different): {$m['accountHolder']}\n\n";
    $text .= 'New-Member Free Shirt Claimed: ' . ($o['claimFree'] ? 'YES — kit manager must check eligibility and previous use manually.' : 'No') . "\n\n";
    $text .= kitOrderLinesSummary($o);
    $text .= "\nFulfilment: {$o['fulfilment']}\n";
    foreach ($o['address'] as $key => $value) $text .= "$key: $value\n";
    $text .= 'Delivery Charge: ' . kitMoney($o['deliveryPence']) . "\n";
    $text .= "\nAmount Due: " . kitMoney($o['totalPence']) . "\nNotes: {$m['notes']}\n";
    return $text;
}

function kitBankInstructions(array $o, array $c): string
{
    return "\nPlease pay by bank transfer\nAccount Name: {$c['bank']['accountName']}\nSort Code: {$c['bank']['sortCode']}\nAccount No.: {$c['bank']['accountNumber']}\nAmount: " . kitMoney($o['totalPence']) . "\n\nPlease use {$o['reference']} as the payment reference.\n\nYour order is Payment Pending. We will process it once payment has been matched and contact you when ready for collection or dispatched.\n";
}

function kitNativeMail(string $to, string $subject, string $body, array $headers): bool
{
    return mail($to, $subject, $body, implode("\r\n", $headers));
}
