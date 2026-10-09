<?php
declare(strict_types=1);
require_once __DIR__ . '/kitConfig.php';
require_once __DIR__ . '/kitValidation.php';
require_once __DIR__ . '/kitMail.php';

function kitReferenceSurname(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $surname = $parts ? (string) end($parts) : 'MEMBER';
    $surname = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $surname));
    return $surname !== '' ? substr($surname, 0, 8) : 'MEMBER';
}

/** Generate an 18-character maximum YYMMDD-SURNAME-NN payment reference under a storage lock. */
function kitReference(DateTimeImmutable $now, string $memberName, array $c): string
{
    $date = $now->setTimezone(new DateTimeZone('Europe/London'))->format('ymd');
    $surname = kitReferenceSurname($memberName);
    $lockPath = $c['storageDir'] . '/.reference-' . $date . '.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock || !chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) throw new RuntimeException('Could not allocate an order reference.');
    try {
        $highest = 0;
        foreach (glob($c['storageDir'] . '/' . $date . '-*-*.json') ?: [] as $path) {
            if (preg_match('/-(\d{2})\.json$/', $path, $matches)) $highest = max($highest, (int) $matches[1]);
        }
        if ($highest >= 99) throw new RuntimeException('Order reference sequence exhausted for today.');
        return sprintf('%s-%s-%02d', $date, $surname, $highest + 1);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function kitEncodeOrder(array $order): string
{
    return json_encode($order, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}

/** Exclusive creation prevents collisions and repeat acceptance of the same reviewed order. */
function kitStoreOrder(array $order, array $c): void
{
    $path = $c['storageDir'] . '/' . $order['reference'] . '.json';
    $handle = @fopen($path, 'x');
    if (!$handle) throw new RuntimeException('Could not save this order.');
    try {
        if (!chmod($path, 0600)) throw new RuntimeException('Could not protect this order.');
        $json = kitEncodeOrder($order);
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('Could not save this order.');
        if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('Could not flush this order.');
    } catch (Throwable $e) {
        fclose($handle);
        unlink($path);
        throw $e;
    }
    fclose($handle);
}

function kitUpdateOrder(array $order, array $c): void
{
    $tmp = tempnam($c['storageDir'], '.order-');
    if (!$tmp) throw new RuntimeException('Could not update order notification status.');
    try {
        $json = kitEncodeOrder($order);
        if (!chmod($tmp, 0600) || file_put_contents($tmp, $json) !== strlen($json) || !rename($tmp, $c['storageDir'] . '/' . $order['reference'] . '.json')) {
            throw new RuntimeException('Could not update order notification status.');
        }
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
}

/** Only the external mail boundary is substituted by tests. Locks prevent concurrent retries. */
function kitNotify(string $reference, array $c, ?callable $mail = null): array
{
    if (!preg_match('/^\d{6}-[A-Z0-9]{1,8}-\d{2}$/', $reference)) throw new RuntimeException('Invalid order reference.');
    $lockPath = $c['storageDir'] . '/' . $reference . '.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock || !chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock the order.');
    try {
        $o = json_decode((string) file_get_contents($c['storageDir'] . '/' . $reference . '.json'), true, 32, JSON_THROW_ON_ERROR);
        $mail = $mail ?? 'kitNativeMail';
        $headers = ['From: HWFC Website <' . $c['fromEmail'] . '>', 'Content-Type: text/plain; charset=UTF-8'];
        if ($o['managerMail'] !== 'sent') {
            try {
                $sent = $mail($c['managerEmail'], 'HWFC Kit Order ' . $reference . ' – ' . $o['member']['name'], kitManagerSummary($o), array_merge($headers, ['Reply-To: ' . $o['member']['email']]));
            } catch (Throwable $e) {
                $sent = false;
            }
            $o['managerMail'] = $sent ? 'sent' : 'failed';
            $o['managerAttempts']++;
            $o['lastAttemptAt'] = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
            kitUpdateOrder($o, $c);
            if (!$sent) error_log('HWFC kit manager notification failed: ' . $reference);
        }
        if ($o['managerMail'] === 'sent' && $o['memberMail'] !== 'sent') {
            try {
                $sent = $mail($o['member']['email'], 'Your HWFC Kit Order ' . $reference, kitMemberSummary($o) . kitBankInstructions($o, $c), $headers);
            } catch (Throwable $e) {
                $sent = false;
            }
            $o['memberMail'] = $sent ? 'sent' : 'failed';
            kitUpdateOrder($o, $c);
        }
        return $o;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function kitAcceptOrder(array $input, array $c, DateTimeImmutable $now): array
{
    $result = kitValidateOrder($input, $c, $now);
    if ($result['errors']) throw new InvalidArgumentException(implode(' ', $result['errors']));
    $o = $result['order'];
    $o += [
        'reference'=>kitReference($now, $o['member']['name'], $c),
        'batchId'=>$c['window']['id'],
        'batchName'=>$c['window']['name'] ?? $c['window']['id'],
        'submittedAt'=>$now->format(DateTimeInterface::ATOM),
        'status'=>'Payment Pending',
        'managerMail'=>'pending',
        'memberMail'=>'pending',
        'managerAttempts'=>0,
    ];
    kitStoreOrder($o, $c);
    return $o;
}
