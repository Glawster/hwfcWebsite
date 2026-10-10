<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lib/kitOrder.php';
try {
    $c = kitLoadConfig();
    $failed = false;
    foreach (glob($c['storageDir'] . '/*.json') as $path) {
        $reference = basename($path, '.json');
        if (!preg_match('/^\d{6}-[A-Z0-9]{1,8}-\d{2}$/', $reference)) continue;
        $o = kitNotify($reference, $c);
        echo $reference . ': manager=' . $o['managerMail'] . ', member=' . $o['memberMail'] . PHP_EOL;
        if ($o['managerMail'] !== 'sent' || $o['memberMail'] !== 'sent') $failed = true;
    }
    exit($failed ? 1 : 0);
} catch (Throwable $e) { fwrite(STDERR, "Kit notification retry failed; check private configuration and storage.\n"); exit(1); }
