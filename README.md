# Hillsborough Walking Football Club Website

Website for Hillsborough Walking Football Club.

The site is intended to be the authoritative source of permanent club information, while Facebook remains the club's main community and regular-content channel.

## Phase 1 Structure

- `/` — Home
- `/play/` — Walking football information, sessions, costs and first-visit guidance
- `/mens/` — Men's walking football
- `/ladies/` — Ladies' walking football
- `/about/` — About HWFC
- `/contact/` — Club contact form and contact routes

Design and content specifications are stored under `documentation/`.

## Current Status

Phase 1 site implementation is now in place.

The implementation is deliberately lightweight:

- Plain HTML.
- Shared responsive CSS.
- No JavaScript framework.
- A small amount of progressive JavaScript on the Contact page for query-string preselection and form status messages.
- PHP contact-form handler for cPanel hosting.

The approved Hillsborough club badge is stored at `assets/hwfc-logo.png` and appears in
the shared page header and browser icon. The adjacent club name remains visible for clear
identification and accessibility.

The About page also deliberately leaves the formal Hillsborough Boys FC relationship, affiliations and proposed values visibly unconfirmed until the club confirms them.

## Contact Form

The form posts to `contact/send.php` and performs server-side validation before using PHP `mail()` to send the enquiry to:

`info@hillsboroughwalkingfootball.com`

It also includes a simple honeypot field for basic bot filtering.

The form should be tested on the production cPanel host because successful delivery depends on the server's PHP mail configuration and domain mail setup.

## Deployment

The production site is hosted at:

`hillsboroughwalkingfootball.com`

The live document root is intended to be a checkout of this repository. Deployment can therefore remain a simple pull from `main` once changes have been reviewed.
