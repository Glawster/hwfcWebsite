<?php

declare(strict_types=1);

$activeNav = $activeNav ?? '';
$navItems = [
    'home' => ['/', 'Home'],
    'play' => ['/play/', 'Play'],
    'mens' => ['/mens/', "Men's"],
    'ladies' => ['/ladies/', "Ladies'"],
    'members' => ['/members/', 'Members'],
    'kit' => ['/kit/', 'Kit'],
    'about' => ['/about/', 'About'],
    'contact' => ['/contact/', 'Contact'],
];
?>
<a class="skipLink" href="#mainContent">Skip to main content</a>
<header class="siteHeader">
    <div class="shell headerInner">
        <a class="brand" href="/" aria-label="Hillsborough Walking Football Club home">
            <img class="brandLogo" src="/assets/hwfc-logo.png" alt="" width="64" height="64">
            <span class="brandName">Hillsborough Walking Football Club</span>
        </a>
        <nav class="mainNav" aria-label="Main navigation">
            <?php foreach ($navItems as $key => [$href, $label]): ?>
                <a<?= $activeNav === $key ? ' aria-current="page"' : '' ?> href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
