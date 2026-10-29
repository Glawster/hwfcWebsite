<?php

declare(strict_types=1);

$activeNav = 'kit';

ob_start();
require __DIR__ . '/page.php';
$html = (string) ob_get_clean();

ob_start();
require __DIR__ . '/../includes/site-header.php';
$header = (string) ob_get_clean();

ob_start();
require __DIR__ . '/../includes/site-footer.php';
$footer = (string) ob_get_clean();

$html = preg_replace(
    '~<a class="skipLink" href="#mainContent">Skip to main content</a>\s*<header class="siteHeader">.*?</header>~s',
    $header,
    $html,
    1
);
$html = preg_replace('~<footer class="siteFooter">.*?</footer>~s', $footer, $html, 1);

if ($html === null) {
    http_response_code(500);
    exit('Page content could not be rendered.');
}

echo $html;
