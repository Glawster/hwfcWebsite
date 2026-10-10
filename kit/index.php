<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/kitOrder.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_set_cookie_params(['httponly'=>true, 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite'=>'Lax', 'path'=>'/kit/']);
session_start();
$_SESSION['kit_csrf'] ??= bin2hex(random_bytes(24));
$errors = []; $input = []; $review = null; $receipt = null; $c = null;
function e($value): string { return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function value(string $key): string { global $input; return e(kitText($input, $key)); }
function kitReceiptRedirect(): void { header('Location: /kit/?confirmation=1', true, 303); exit; }
try { $c = kitLoadConfig(); } catch (Throwable $exception) { http_response_code(503); error_log('HWFC kit configuration unavailable: ' . $exception->getMessage()); }
$now = new DateTimeImmutable();
$state = $c ? kitWindowState($c, $now) : 'unavailable';
if ($c && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['kit_csrf'], $_POST['csrf'])) {
        $errors[] = 'Your form session expired. Please reload the page and try again.'; http_response_code(422);
    } elseif (($_POST['action'] ?? '') === 'confirm') {
        $saved = $_SESSION['kit_review'] ?? null;
        if (is_string($_POST['review_token'] ?? null) && isset($_SESSION['kit_receipt_token']) && hash_equals($_SESSION['kit_receipt_token'], $_POST['review_token'])) kitReceiptRedirect();
        if (!$saved || !is_string($_POST['review_token'] ?? null) || !hash_equals($saved['token'], $_POST['review_token'])) {
            $errors[] = 'Please review your order before submitting it.';
        } else {
            $input = $saved['input'];
            $result = kitValidateOrder($input, $c, $now);
            $errors = $result['errors'];
            if ($saved['configHash'] !== hash('sha256', json_encode($c))) $errors[] = 'Kit settings changed. Please check and review your order again.';
            if (!$errors) {
                try {
                    $receipt = kitAcceptOrder($input, $c, $now);
                    $_SESSION['kit_receipt'] = $receipt;
                    $_SESSION['kit_receipt_bank'] = $c['bank'];
                    $_SESSION['kit_receipt_token'] = $saved['token'];
                    unset($_SESSION['kit_review']);
                    try { $_SESSION['kit_receipt'] = kitNotify($receipt['reference'], $c); }
                    catch (Throwable $exception) { error_log('HWFC kit notification requires recovery: ' . $receipt['reference']); }
                    kitReceiptRedirect();
                } catch (Throwable $exception) { $errors[] = 'We could not save your order. No order has been accepted. Please try again or contact the club.'; http_response_code(503); }
            }
        }
    } else {
        $input = $_POST;
        if (($_POST['action'] ?? '') !== 'edit') {
            $result = kitValidateOrder($input, $c, $now);
            $errors = $result['errors'];
            if (!$errors) {
                $review = $result['order'];
                $_SESSION['kit_review'] = ['input'=>kitCanonicalInput($review), 'token'=>bin2hex(random_bytes(24)), 'configHash'=>hash('sha256', json_encode($c))];
            }
        } elseif (isset($_SESSION['kit_review'])) $input = $_SESSION['kit_review']['input'];
    }
    if ($errors && http_response_code() === 200) http_response_code(422);
}
if (isset($_GET['confirmation']) && isset($_SESSION['kit_receipt'])) {
    $receipt = $_SESSION['kit_receipt'];
    if ($c) $c['bank'] = $_SESSION['kit_receipt_bank'];
}
function renderOrder(array $o): void {
?>
<p><strong>New-Member Free-Shirt Claim:</strong> <?= $o['claimFree'] ? 'Yes — the kit manager will check eligibility and previous use.' : 'No' ?></p>
<div class="kitSummary">
<?php foreach ($o['lines'] as $line): ?>
<article class="infoCard"><h3><?= e($line['name']) ?></h3><p>Size: <?= e($line['size']) ?> · Quantity: <?= e($line['quantity']) ?><?php if ($line['initials'] !== ''): ?> · Supplier-Applied Initials: <?= e($line['initials']) ?><?php endif ?></p>
<p>Unit Base Price: <?= e(kitMoney($line['unitPence'])) ?><?php if ($line['initialsPence'] > 0): ?> · Initials Per Unit: <?= e(kitMoney($line['initialsPence'])) ?><?php endif ?></p>
<?php if ($line['freeUnits']): ?><p><strong>New Member Free Shirt – £0.00</strong> (one base shirt unit; additional units and initials remain payable).</p><?php endif ?>
<p><strong>Line Total: <?= e(kitMoney($line['totalPence'])) ?></strong></p></article>
<?php endforeach ?></div>
<p>Fulfilment: <?= e(ucfirst($o['fulfilment'])) ?> · Delivery Charge: <?= e(kitMoney($o['deliveryPence'])) ?></p>
<?php if ($o['address']): ?><p><?php foreach ($o['address'] as $key=>$v): if ($v !== ''): ?><?= e(ucfirst($key)) ?>: <?= e($v) ?><br><?php endif; endforeach ?></p><?php endif ?>
<p><strong>Order Total: <?= e(kitMoney($o['totalPence'])) ?></strong></p>
<p><?= e($o['member']['name']) ?><br><?= e($o['member']['email']) ?><br><?= e($o['member']['phone']) ?></p>
<p>Section: <?= e($o['member']['section']) ?><?php if ($o['member']['accountHolder'] !== ''): ?> · Account Holder: <?= e($o['member']['accountHolder']) ?><?php endif ?></p>
<p>Order Notes: <?= nl2br(e($o['member']['notes'])) ?></p>
<?php }
?>
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Club Shop | HWFC</title><link rel="icon" type="image/png" href="/assets/hwfc-logo.png"><link rel="stylesheet" href="/styles.css"><script defer src="/kit/order.js"></script></head>
<body><a class="skipLink" href="#mainContent">Skip to main content</a>
<header class="siteHeader"><div class="shell headerInner"><a class="brand" href="/" aria-label="Hillsborough Walking Football Club home"><img class="brandLogo" src="/assets/hwfc-logo.png" alt="" width="64" height="64"><span class="brandName">Hillsborough Walking Football Club</span></a><nav class="mainNav" aria-label="Main navigation"><a href="/">Home</a><a href="/play/">Play</a><a href="/mens/">Men's</a><a href="/ladies/">Ladies'</a><a aria-current="page" href="/kit/">Kit</a><a href="/about/">About</a><a href="/contact/">Contact</a></nav></div></header>
<main id="mainContent"><section class="pageHero kitHero"><div class="shell narrow"><h1>Club Shop</h1>
<?php if ($state === 'open'): ?><p class="infoStrip"><strong>Orders for this kit window close: <?= e(kitDate($c['window']['closes'])) ?>.</strong></p><?php endif ?></div></section>
<section class="section"><div class="shell narrow">
<?php if ($errors): ?><div class="notice noticeError" role="alert"><h2>Please Check Your Order</h2><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if ($receipt): ?>
<h2>Thank You – Your Kit Order Has Been Received</h2><p><strong>Order Reference: <?= e($receipt['reference']) ?><br>Amount to Pay: <?= e(kitMoney($receipt['totalPence'])) ?></strong></p>
<p>Batch: <?= e($receipt['batchId']) ?> — <?= e($receipt['batchName']) ?><br>Submitted: <?= e(kitDate($receipt['submittedAt'])) ?></p>
<?php if ($receipt['managerMail'] !== 'sent'): ?><div class="notice noticeError" role="status">Your order has been saved, but we could not confirm the kit-manager email notification. Please contact the club with your order reference; the club can recover and retry the saved notification. Please do not submit the order again.</div><?php else: ?><p>The mail service accepted the kit-manager notification.</p><?php endif ?>
<?php if ($receipt['memberMail'] !== 'sent'): ?><p>Your complete confirmation is shown here. Please save or print it; we could not confirm a confirmation email.</p><?php endif ?>
<?php renderOrder($receipt); ?>
<?php if ($c): ?><h2>Pay by Bank Transfer</h2><p>Account Name: <?= e($c['bank']['accountName']) ?><br>Sort Code: <?= e($c['bank']['sortCode']) ?><br>Account No.: <?= e($c['bank']['accountNumber']) ?><br>Amount: <?= e(kitMoney($receipt['totalPence'])) ?><br>Reference: <?= e($receipt['reference']) ?></p><p><strong>Please use <?= e($receipt['reference']) ?> as the payment reference.</strong></p><?php else: ?><p>Payment instructions are temporarily unavailable. Contact the club with your reference.</p><?php endif ?>
<p>Your order is currently <strong>Payment Pending</strong>. We will process the order once the payment has been matched. We will contact you if we need any further information and again when the order is ready for collection or has been dispatched.</p>
<?php elseif ($state !== 'open'): ?>
<div class="notice" role="status"><?php if ($state === 'before'): ?>Ordering opens <?= e(kitDate($c['window']['opens'])) ?>.<?php elseif ($state === 'closed'): ?>This kit-order window has closed. Please contact the club about the next window.<?php else: ?>Kit ordering is currently unavailable. Please contact the club for help.<?php endif ?></div>
<?php elseif ($review): ?>
<h2>Check Your Order</h2><?php renderOrder($review); ?>
<p>No order has been accepted yet. Check your sizes, initials and details before submitting.</p>
<form method="post" action="/kit/"><input type="hidden" name="csrf" value="<?= e($_SESSION['kit_csrf']) ?>"><input type="hidden" name="review_token" value="<?= e($_SESSION['kit_review']['token']) ?>"><div class="buttonRow"><button class="button buttonSecondary" name="action" value="edit" formnovalidate>Back / Change Order</button><button class="button buttonPrimary" name="action" value="confirm">Submit Kit Order</button></div></form>
<?php else: ?>
<form id="kitForm" action="/kit/" method="post" novalidate><input type="hidden" name="csrf" value="<?= e($_SESSION['kit_csrf']) ?>"><input type="hidden" name="action" value="review">
<h2>Kit</h2>
<?php $index=0; foreach ($c['products'] as $id=>$p):
$raw = [];
foreach (is_array($input['lines'] ?? null) ? $input['lines'] : [] as $candidate) {
    if (is_array($candidate) && kitText($candidate, 'product') === $id) { $raw = $candidate; break; }
}
?>
<article class="infoCard kitProduct" data-price="<?= e($p['pricePence']) ?>" data-initials-price="<?= e($p['initialsPence']) ?>" data-free="<?= $p['freeShirt'] ? 'yes' : 'no' ?>" data-name="<?= e($p['name']) ?>">
<div class="kitProductInfo">
<?php if (!empty($p['image'])): ?><img class="kitImage" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"><?php endif ?>
<h3><?= e($p['name']) ?></h3>
<?php if ($p['description'] !== ''): ?><p><?= e($p['description']) ?></p><?php endif ?>
<p class="kitPrice"><strong><?= e(kitMoney($p['pricePence'])) ?></strong></p>
<?php if ($p['initials'] && $p['initialsPence'] > 0): ?><p class="formNote">Initials: <?= e(kitMoney($p['initialsPence'])) ?> per item.</p><?php endif ?>
<?php if ($p['freeShirt']): ?><p class="formNote">Eligible for the new-member free shirt.</p><?php endif ?>
</div>
<div class="kitProductOrder kitLine"><input type="hidden" name="lines[<?= $index ?>][product]" value="<?= e($id) ?>">
<div class="field"><label for="size-<?= $index ?>">Size</label><select id="size-<?= $index ?>" name="lines[<?= $index ?>][size]"><option value="">Choose size</option><?php foreach ($p['sizes'] as $size): ?><option value="<?= e($size) ?>" <?= kitText($raw, 'size') === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach ?></select></div>
<div class="field"><label for="quantity-<?= $index ?>">Quantity</label><input id="quantity-<?= $index ?>" type="number" name="lines[<?= $index ?>][quantity]" min="0" max="<?= e($p['maxQuantity']) ?>" step="1" value="<?= e(kitText($raw, 'quantity') ?: '0') ?>"></div>
<?php if ($p['initials']): ?><div class="field"><label for="initials-<?= $index ?>">Initials</label><input id="initials-<?= $index ?>" name="lines[<?= $index ?>][initials]" maxlength="4" pattern="[A-Za-z]{1,4}" autocomplete="off" aria-label="Supplier initials" value="<?= e(kitText($raw, 'initials')) ?>"></div><?php endif ?>
</div></article>
<?php $index++; endforeach ?>
<h2>New Member Free Shirt</h2><div class="humanCheck"><input id="claimFree" type="checkbox" name="claimFree" value="yes" <?= ($input['claimFree'] ?? '') === 'yes' ? 'checked' : '' ?>><label for="claimFree">I am a new member and I am claiming my one free club shirt</label></div><p>New members are entitled to one free club shirt. The kit manager will check eligibility before the supplier order is placed.</p>
<aside class="infoStrip" id="runningOrder" aria-live="polite" data-delivery="<?= e($c['deliveryPence']) ?>"><h2>Your Order</h2><div id="runningLines">Your order total will appear here.</div></aside>
<h2>Your Details</h2><div class="formGrid">
<?php foreach (['name'=>['Full Name','text','name',120], 'email'=>['Email Address','email','email',200], 'phone'=>['Telephone Number','tel','tel',50], 'accountHolder'=>['Account Holder (if different)','text','off',120]] as $key=>$field): ?>
<div class="field"><label for="<?= $key ?>"><?= e($field[0]) ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $field[1] ?>" autocomplete="<?= $field[2] ?>" maxlength="<?= $field[3] ?>" value="<?= value($key) ?>" <?= $key !== 'accountHolder' ? 'required' : '' ?>></div><?php endforeach ?>
<div class="field fieldFull"><label for="section">Section (Optional)</label><select id="section" name="section"><option value="">Choose section</option><?php foreach (["Men's", "Ladies'", 'Other', 'Prefer not to say'] as $section): ?><option <?= kitText($input, 'section') === $section ? 'selected' : '' ?>><?= e($section) ?></option><?php endforeach ?></select></div></div>
<p class="formNote">Complete Account Holder only if the bank transfer will come from an account in a different name.</p>
<fieldset class="kitFulfilment"><legend>Collection or Delivery</legend><label class="humanCheck"><input type="radio" name="fulfilment" value="collection" <?= kitText($input,'fulfilment') !== 'delivery' ? 'checked' : '' ?>>Collect from the club</label><label class="humanCheck"><input type="radio" name="fulfilment" value="delivery" <?= kitText($input,'fulfilment') === 'delivery' ? 'checked' : '' ?>>Post / delivery (+<?= e(kitMoney($c['deliveryPence'])) ?>)</label></fieldset>
<?php $deliveryFields = ['recipient'=>'Recipient Name','address1'=>'Address Line 1','address2'=>'Address Line 2','city'=>'Town / City','county'=>'County','postcode'=>'Postcode']; ?>
<div id="deliveryFields" <?= kitText($input,'fulfilment') === 'delivery' ? '' : 'hidden' ?>><div class="formGrid"><?php foreach ($deliveryFields as $key=>$label): ?><div class="field <?= $key === 'address1' ? 'fieldFull' : '' ?>"><label for="<?= e($key) ?>"><?= e($label) ?></label><input id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="120" data-delivery-required="<?= in_array($key,$c['deliveryRequired'],true)?'yes':'no' ?>" value="<?= value($key) ?>"></div><?php endforeach ?></div></div>
<div class="field fieldFull"><label for="notes">Order Notes (Optional)</label><textarea id="notes" name="notes" rows="4" maxlength="1000"><?= value('notes') ?></textarea><p class="formNote">Do not enter bank account, card details or passwords here.</p></div>
<div class="honeypot" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div><div class="humanCheck"><input id="human" type="checkbox" name="human" value="yes" required <?= ($input['human'] ?? '') === 'yes' ? 'checked' : '' ?>><label for="human">I am human</label></div>
<div class="buttonRow"><button class="button buttonPrimary" type="submit">Review Order</button></div></form>
<?php endif ?></div></section></main>
<footer class="siteFooter"><div class="shell footerGrid"><div><p class="footerTitle">Hillsborough Walking Football Club</p><p>Walking football in Hillsborough and Lisburn.</p></div><div><p class="footerTitle">Quick links</p><p><a href="/play/">Play</a> · <a href="/mens/">Men's</a> · <a href="/ladies/">Ladies'</a> · <a href="/kit/">Kit</a> · <a href="/about/">About</a> · <a href="/contact/">Contact</a></p></div></div></footer></body></html>
