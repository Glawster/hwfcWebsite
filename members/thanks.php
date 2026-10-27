<?php

declare(strict_types=1);
session_start();
$reference = $_SESSION['member_application_reference'] ?? null;
unset($_SESSION['member_application_reference']);
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Membership application received | Hillsborough Walking Football Club</title>
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<a class="skipLink" href="#mainContent">Skip to main content</a>
<header class="siteHeader"><div class="shell headerInner"><a class="brand" href="/"><img class="brandLogo" src="/assets/hwfc-logo.png" alt="" width="64" height="64"><span class="brandName">Hillsborough Walking Football Club</span></a><nav class="mainNav" aria-label="Main navigation"><a href="/">Home</a><a href="/play/">Play</a><a href="/mens/">Men's</a><a href="/ladies/">Ladies'</a><a aria-current="page" href="/members/">Members</a><a href="/kit/">Kit</a><a href="/about/">About</a><a href="/contact/">Contact</a></nav></div></header>
<main id="mainContent"><section class="pageHero"><div class="shell narrow"><p class="eyebrow">Membership application</p><h1>Thank you</h1><p class="heroLead">Your membership application has been received and stored securely for review.</p><?php if ($reference): ?><p><strong>Application reference:</strong> <?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><div class="buttonRow"><a class="button buttonPrimary" href="/members/">Back to Members</a><a class="button buttonSecondary" href="/contact/">Contact HWFC</a></div></div></section></main>
<footer class="siteFooter"><div class="shell footerGrid"><div><strong>Hillsborough Walking Football Club</strong><p>Football for life.</p></div><div><a href="/play/">Play</a><a href="/mens/">Men's</a><a href="/ladies/">Ladies'</a><a href="/members/">Members</a></div><div><a href="/about/">About</a><a href="/contact/">Contact</a><a href="/privacy/">Privacy Policy</a><a href="mailto:info@hillsboroughwalkingfootball.com">info@hillsboroughwalkingfootball.com</a></div></div></footer>
</body>
</html>
