<?php
declare(strict_types=1);

function kitText(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
}

/** Validate untrusted input and calculate exclusively from the configured catalogue. */
function kitValidateOrder(array $input, array $c, DateTimeImmutable $now): array
{
    $errors = [];
    if (kitWindowState($c, $now) !== 'open') $errors[] = 'This kit window is not open. New orders cannot be accepted.';
    if (kitText($input, 'website') !== '' || is_array($input['website'] ?? null)) $errors[] = 'This submission could not be accepted.';
    if (($input['human'] ?? null) !== 'yes') $errors[] = 'Please confirm that you are human.';

    $member = [];
    foreach (['name' => 120, 'email' => 200, 'phone' => 50, 'section' => 40, 'accountHolder' => 120, 'notes' => 2000] as $key => $max) {
        $member[$key] = kitText($input, $key);
        if (strlen($member[$key]) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $member[$key]) || ($key !== 'notes' && preg_match('/[\r\n]/', $member[$key]))) {
            $errors[] = 'Please check the ' . $key . ' field and its length.';
        }
    }
    if ($member['name'] === '') $errors[] = 'Please enter your Full Name.';
    if (!filter_var($member['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[+()0-9 .-]{6,50}$/', $member['phone']) || strlen(preg_replace('/\D/', '', $member['phone'])) < 6) $errors[] = 'Please enter a valid contact telephone number.';
    if (!in_array($member['section'], ['', "Men's", "Ladies'", 'Other', 'Prefer not to say'], true)) $errors[] = 'Please choose a listed section.';

    $claim = ($input['claimFree'] ?? '') === 'yes';
    if (!in_array($input['claimFree'] ?? '', ['', 'yes'], true)) $errors[] = 'Please check the new-member free-shirt claim.';

    $method = kitText($input, 'fulfilment');
    if (!in_array($method, ['collection', 'delivery'], true)) $errors[] = 'Please choose collection or delivery.';
    $address = [];
    if ($method === 'delivery') {
        foreach (['recipient', 'address1', 'address2', 'city', 'county', 'postcode'] as $key) {
            $address[$key] = kitText($input, $key);
            if (strlen($address[$key]) > 160 || preg_match('/[\x00-\x1f\x7f]/', $address[$key])) $errors[] = 'Please check the delivery ' . $key . '.';
            if (in_array($key, $c['deliveryRequired'], true) && $address[$key] === '') {
                $errors[] = 'Please enter delivery ' . ['address1'=>'address line 1', 'city'=>'town / city', 'postcode'=>'postcode', 'recipient'=>'recipient name', 'address2'=>'address line 2', 'county'=>'county'][$key] . '.';
            }
        }
    }

    $rawLines = $input['lines'] ?? [];
    if (!is_array($rawLines) || count($rawLines) > 60) { $errors[] = 'Please check the number of order lines.'; $rawLines = []; }
    $lines = []; $discountUsed = false; $total = 0; $seenProducts = [];
    foreach ($rawLines as $raw) {
        if (!is_array($raw)) { $errors[] = 'Please check your kit items.'; continue; }
        $quantityText = kitText($raw, 'quantity');
        if ($quantityText === '' || $quantityText === '0') continue;
        $id = kitText($raw, 'product');
        if (!isset($c['products'][$id])) { $errors[] = 'Please select a listed kit product.'; continue; }
        $p = $c['products'][$id];
        if (isset($seenProducts[$id])) { $errors[] = 'Please order ' . $p['name'] . ' only once.'; continue; }
        $seenProducts[$id] = true;

        if (!preg_match('/^[1-9][0-9]?$/', $quantityText) || (int) $quantityText > $p['maxQuantity']) {
            $errors[] = 'Please enter a whole quantity between 1 and ' . $p['maxQuantity'] . ' for ' . $p['name'] . '.';
            continue;
        }
        $quantity = (int) $quantityText;
        $size = kitText($raw, 'size');
        if (!in_array($size, $p['sizes'], true)) $errors[] = 'Please choose a listed size for ' . $p['name'] . '.';

        $initials = kitText($raw, 'initials');
        if ($p['initials'] && $initials === '') $errors[] = 'Please enter the initials required for ' . $p['name'] . '.';
        if (!$p['initials'] && $initials !== '') $errors[] = $p['name'] . ' does not support initials.';
        if ($initials !== '' && !preg_match('/^[A-Za-z]{1,4}$/', $initials)) $errors[] = 'Please enter one to four letters for the initials on ' . $p['name'] . '.';
        if (isset($raw['shirtNumber'])) $errors[] = 'Shirt numbers are not supported.';

        $free = $claim && !$discountUsed && $p['freeShirt'];
        if ($free) $discountUsed = true;
        $personalisation = $initials !== '' ? $p['initialsPence'] : 0;
        $lineTotal = $quantity * ($p['pricePence'] + $personalisation) - ($free ? $p['pricePence'] : 0);
        $lines[] = [
            'product'=>$id,
            'name'=>$p['name'],
            'size'=>$size,
            'quantity'=>$quantity,
            'initials'=>$initials,
            'unitPence'=>$p['pricePence'],
            'initialsPence'=>$personalisation,
            'freeUnits'=>$free ? 1 : 0,
            'totalPence'=>$lineTotal,
        ];
        $total += $lineTotal;
    }

    if (!$lines) $errors[] = 'Please select at least one kit item.';
    if ($claim && !$discountUsed) $errors[] = 'You have claimed your free new-member shirt; please add an eligible club shirt to your order.';
    if (isset($input['shirtNumber'])) $errors[] = 'Shirt numbers are not supported.';

    $delivery = $method === 'delivery' ? $c['deliveryPence'] : 0;
    return ['errors'=>array_values(array_unique($errors)), 'order'=>[
        'member'=>$member,
        'lines'=>$lines,
        'claimFree'=>$claim,
        'fulfilment'=>$method,
        'address'=>$address,
        'deliveryPence'=>$delivery,
        'totalPence'=>$total + $delivery,
    ]];
}

/** Keep only validated form fields in the review session; never retain arbitrary posted fields. */
function kitCanonicalInput(array $order): array
{
    $input = $order['member'] + $order['address'];
    $input += ['human'=>'yes', 'website'=>'', 'fulfilment'=>$order['fulfilment'], 'claimFree'=>$order['claimFree'] ? 'yes' : '', 'lines'=>[]];
    foreach ($order['lines'] as $line) {
        $input['lines'][] = ['product'=>$line['product'], 'size'=>$line['size'], 'quantity'=>(string) $line['quantity'], 'initials'=>$line['initials']];
    }
    return $input;
}
