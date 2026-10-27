<?php

declare(strict_types=1);

session_start();
if (empty($_SESSION['member_csrf'])) {
    $_SESSION['member_csrf'] = bin2hex(random_bytes(24));
}
$errors = $_SESSION['member_errors'] ?? [];
$old = $_SESSION['member_old'] ?? [];
unset($_SESSION['member_errors'], $_SESSION['member_old']);

function old(string $key): string
{
    global $old;
    return htmlspecialchars((string) ($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function selected(string $key, string $value): string
{
    global $old;
    return (($old[$key] ?? '') === $value) ? ' selected' : '';
}

function checked(string $key, string $value): string
{
    global $old;
    return (($old[$key] ?? '') === $value) ? ' checked' : '';
}

function errorFor(string $key): string
{
    global $errors;
    if (!isset($errors[$key])) {
        return '';
    }
    return '<p class="formNote" role="alert">' . htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8') . '</p>';
}
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Members | Hillsborough Walking Football Club</title>
    <meta name="description" content="Apply for Hillsborough Walking Football Club membership or update existing member details.">
    <link rel="icon" type="image/png" href="/assets/hwfc-logo.png">
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<a class="skipLink" href="#mainContent">Skip to main content</a>
<header class="siteHeader"><div class="shell headerInner"><a class="brand" href="/" aria-label="Hillsborough Walking Football Club home"><img class="brandLogo" src="/assets/hwfc-logo.png" alt="" width="64" height="64"><span class="brandName">Hillsborough Walking Football Club</span></a><nav class="mainNav" aria-label="Main navigation"><a href="/">Home</a><a href="/play/">Play</a><a href="/mens/">Men's</a><a href="/ladies/">Ladies'</a><a aria-current="page" href="/members/">Members</a><a href="/kit/">Kit</a><a href="/about/">About</a><a href="/contact/">Contact</a></nav></div></header>
<main id="mainContent">
<section class="pageHero"><div class="shell narrow"><p class="eyebrow">HWFC membership</p><h1>Members</h1><p class="heroLead">Apply to join Hillsborough Walking Football Club, or keep the details we already hold about you up to date.</p><div class="buttonRow"><a class="button buttonPrimary" href="#application">Apply for membership</a><a class="button buttonSecondary" href="https://docs.google.com/forms/d/e/1FAIpQLScyUqbWcvOLKTFX0r_Er9qADa1iV08DMU2hHMEUonuDbf6NBw/viewform" target="_blank" rel="noopener">Update existing member details</a></div></div></section>

<section class="section sectionSoft"><div class="shell contentGrid"><article class="infoCard"><p class="eyebrow">New members</p><h2>Apply for membership</h2><p>Use the HWFC application below. Your application is stored as a pending membership record for the club to review.</p><p><a class="textLink" href="#application">Go to the application form</a></p></article><article class="infoCard"><p class="eyebrow">Existing members</p><h2>Update your details</h2><p>If you are already a member, use the current member-information form to make sure the club has the details it may need for communication and safety.</p><p><a class="textLink" href="https://docs.google.com/forms/d/e/1FAIpQLScyUqbWcvOLKTFX0r_Er9qADa1iV08DMU2hHMEUonuDbf6NBw/viewform" target="_blank" rel="noopener">Open the existing-member form</a></p></article></div></section>

<section class="section" id="application"><div class="shell narrow"><p class="eyebrow">Membership application</p><h2>Apply to join HWFC</h2><p class="lead">Please provide the information the club needs to administer membership and support safe participation.</p><div class="infoStrip"><strong>Privacy:</strong> HWFC will use this information to administer your membership and club participation. Please read our <a class="textLink" href="/privacy/" target="_blank" rel="noopener">Privacy Policy</a> before submitting. Health information should be limited to information relevant to your safety or participation.</div>
<?php if ($errors): ?><div class="notice noticeError" role="alert"><strong>Please check the highlighted form information and try again.</strong></div><?php endif; ?>
<form method="post" action="/members/submit.php">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['member_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

<h3>Your details</h3>
<div class="formGrid">
<div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="<?= old('first_name') ?>" autocomplete="given-name" required><?= errorFor('first_name') ?></div>
<div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="<?= old('last_name') ?>" autocomplete="family-name" required><?= errorFor('last_name') ?></div>
<div class="field"><label for="preferred_name">Preferred name <span class="formNote">(optional)</span></label><input id="preferred_name" name="preferred_name" value="<?= old('preferred_name') ?>"></div>
<div class="field"><label for="date_of_birth">Date of birth</label><input type="date" id="date_of_birth" name="date_of_birth" value="<?= old('date_of_birth') ?>" autocomplete="bday" required><?= errorFor('date_of_birth') ?></div>
<div class="field fieldFull"><label for="address">Home address</label><textarea id="address" name="address" autocomplete="street-address" required><?= old('address') ?></textarea><?= errorFor('address') ?></div>
<div class="field"><label for="postcode">Postcode</label><input id="postcode" name="postcode" value="<?= old('postcode') ?>" autocomplete="postal-code" required><?= errorFor('postcode') ?></div>
<div class="field"><label for="phone">Telephone number</label><input id="phone" name="phone" value="<?= old('phone') ?>" autocomplete="tel" inputmode="tel" required><?= errorFor('phone') ?></div>
<div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required><?= errorFor('email') ?></div>
<div class="field"><label for="preferred_contact">Preferred contact method</label><select id="preferred_contact" name="preferred_contact" required><option value="">Choose…</option><option value="phone"<?= selected('preferred_contact','phone') ?>>Phone call</option><option value="text"<?= selected('preferred_contact','text') ?>>Text message</option><option value="whatsapp"<?= selected('preferred_contact','whatsapp') ?>>WhatsApp</option><option value="email"<?= selected('preferred_contact','email') ?>>Email</option></select><?= errorFor('preferred_contact') ?></div>
<div class="field"><label for="membership_section">Which section applies?</label><select id="membership_section" name="membership_section" required><option value="">Choose…</option><option value="mens"<?= selected('membership_section','mens') ?>>Men's</option><option value="ladies"<?= selected('membership_section','ladies') ?>>Ladies'</option><option value="unsure"<?= selected('membership_section','unsure') ?>>Not sure / please advise</option></select><?= errorFor('membership_section') ?></div>
</div>

<h3>Emergency contact</h3>
<p class="formNote">Please tell your nominated emergency contact that you have supplied their details to HWFC.</p>
<div class="formGrid">
<div class="field"><label for="emergency_name">Emergency contact full name</label><input id="emergency_name" name="emergency_name" value="<?= old('emergency_name') ?>" required><?= errorFor('emergency_name') ?></div>
<div class="field"><label for="emergency_relationship">Relationship to you</label><input id="emergency_relationship" name="emergency_relationship" value="<?= old('emergency_relationship') ?>" required><?= errorFor('emergency_relationship') ?></div>
<div class="field"><label for="emergency_phone">Emergency contact telephone</label><input id="emergency_phone" name="emergency_phone" value="<?= old('emergency_phone') ?>" inputmode="tel" required><?= errorFor('emergency_phone') ?></div>
<div class="field"><label for="second_emergency_name">Second emergency contact <span class="formNote">(optional)</span></label><input id="second_emergency_name" name="second_emergency_name" value="<?= old('second_emergency_name') ?>"></div>
<div class="field"><label for="second_emergency_phone">Second emergency contact telephone <span class="formNote">(optional)</span></label><input id="second_emergency_phone" name="second_emergency_phone" value="<?= old('second_emergency_phone') ?>" inputmode="tel"></div>
</div>

<h3>Health and participation</h3>
<fieldset class="kitFulfilment"><legend>Do you have any health conditions, allergies, medication, mobility needs or other information relevant to your safety or participation in walking football?</legend><div class="humanCheck"><input type="radio" id="health_no" name="health_relevant" value="no"<?= checked('health_relevant','no') ?> required><label for="health_no">No</label></div><div class="humanCheck"><input type="radio" id="health_yes" name="health_relevant" value="yes"<?= checked('health_relevant','yes') ?>><label for="health_yes">Yes</label></div><?= errorFor('health_relevant') ?></fieldset>
<div class="field"><label for="health_details">If yes, provide only the information relevant to your safety or participation</label><textarea id="health_details" name="health_details"><?= old('health_details') ?></textarea><?= errorFor('health_details') ?></div>

<h3>Photography and publicity</h3>
<fieldset class="kitFulfilment"><legend>Do you consent to being photographed at club events and for photographs or video to be used for club publicity?</legend><div class="humanCheck"><input type="radio" id="photo_yes" name="photo_consent" value="yes"<?= checked('photo_consent','yes') ?> required><label for="photo_yes">Yes</label></div><div class="humanCheck"><input type="radio" id="photo_no" name="photo_consent" value="no"<?= checked('photo_consent','no') ?>><label for="photo_no">No</label></div><?= errorFor('photo_consent') ?></fieldset>
<p class="formNote">This choice is separate from the information required to administer membership and can be changed later.</p>

<div class="humanCheck"><input type="checkbox" id="accuracy_confirmed" name="accuracy_confirmed" value="yes"<?= checked('accuracy_confirmed','yes') ?> required><label for="accuracy_confirmed">I confirm that the information provided is accurate to the best of my knowledge.</label></div><?= errorFor('accuracy_confirmed') ?>
<p class="formNote">Submitting this form does not mean you are consenting to all data processing. HWFC processes necessary membership information under the lawful bases described in the <a class="textLink" href="/privacy/">Privacy Policy</a>.</p>
<div class="buttonRow"><button class="button buttonPrimary" type="submit">Submit membership application</button></div>
</form></div></section>
</main>
<footer class="siteFooter"><div class="shell footerGrid"><div><strong>Hillsborough Walking Football Club</strong><p>Football for life.</p></div><div><a href="/play/">Play</a><a href="/mens/">Men's</a><a href="/ladies/">Ladies'</a><a href="/members/">Members</a></div><div><a href="/about/">About</a><a href="/contact/">Contact</a><a href="/privacy/">Privacy Policy</a><a href="mailto:info@hillsboroughwalkingfootball.com">info@hillsboroughwalkingfootball.com</a></div></div></footer>
</body>
</html>
