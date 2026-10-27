# Membership operations

## 0.6 scope

Version 0.6 introduces the public `/members/` section and the first database-backed membership workflow for REQ-004.

Included in 0.6:

- public membership application;
- existing-member details link to the current Google Form;
- server-side validation and CSRF protection;
- SQLite membership application database;
- `Pending` application state;
- privacy-notice version and submission timestamp recording;
- separate photography/publicity consent;
- relevant health/safety information only;
- non-personal audit events;
- CLI subject-access export, correction and deletion tooling.

Deferred to 0.7 and later increments:

- authenticated browser administration;
- application acceptance/rejection UI;
- permanent Membership IDs;
- membership periods, expiry and renewals;
- payment tracking and shared bank-transfer instructions;
- spreadsheet/vCard import/export;
- virtual membership cards.

REQ-003 Admin Authentication must be implemented before private member data is exposed in a browser administration interface.

## Database location

Membership data must not be committed to Git or stored in a publicly downloadable location.

The application reads the database path from `HWFC_MEMBER_DB`.

For production, configure it to a path outside the website document root, for example:

```bash
export HWFC_MEMBER_DB="$HOME/private/hwfc/members.sqlite"
```

The exact cPanel mechanism may be an environment setting, PHP-FPM configuration, or another protected server configuration available on the host.

A local-development fallback exists at `members/data/members.sqlite`. The directory contains an Apache `.htaccess` denial rule and database files are ignored by Git, but this fallback should not be used as the preferred production location.

The database file is chmod `0600` where supported and its containing directory is created with `0700` where supported.

PHP requires PDO SQLite support.

Check with:

```bash
php -m | grep -Ei 'pdo|sqlite'
```

## Data held in 0.6

A pending membership application stores:

- first and last name;
- optional preferred name;
- date of birth;
- home address and postcode;
- email and telephone number;
- preferred contact method;
- explicitly selected membership section;
- emergency contact details;
- optional second emergency contact;
- whether relevant health/safety information exists and the supplied details;
- photography/publicity choice;
- privacy-notice version shown at submission;
- submission/update timestamps;
- application status.

The database does not store member banking credentials or account details.

## GDPR / data-subject operations

The initial GDPR operations are deliberately CLI-only until authenticated administration is implemented.

Run them from the repository root using the same `HWFC_MEMBER_DB` environment value as the website.

### Export data held for a person

```bash
php members/bin/memberData.php export --email=member@example.com
```

The command returns the matching membership application record(s) and associated audit events as JSON. Redirect to a protected file when preparing a subject-access response:

```bash
php members/bin/memberData.php export --email=member@example.com > /secure/path/member-export.json
```

Do not store exports beneath the public web root.

### Correct inaccurate data

Example:

```bash
php members/bin/memberData.php correct \
  --email=member@example.com \
  --field=phone \
  --value='07700 900000'
```

Only an explicit field allow-list can be corrected through this tool. The correction creates a timestamped audit event naming the field changed but does not copy the old/new personal value into the audit log.

### Delete personal membership-application data

Before deletion, export and review the record and determine whether any legal/insurance/other retention requirement applies.

Deletion requires an explicit confirmation flag:

```bash
php members/bin/memberData.php delete --email=member@example.com --confirm
```

The personal application row is deleted. A non-personal audit event containing the application UUID, action and timestamp remains to record that a deletion process occurred.

## Privacy notice version

0.6 records the membership privacy version as `2026-10-27`, corresponding to the privacy policy currently published at `/privacy/`.

When the membership/privacy wording materially changes, update `hwfcPrivacyVersion()` in `members/lib/memberConfig.php` before accepting applications under the revised notice.

## Deployment checks

Before enabling the membership application in production:

1. Confirm PHP has PDO SQLite support.
2. Set `HWFC_MEMBER_DB` to a protected path outside the document root.
3. Confirm the web-server/PHP process can create/write the database and parent directory.
4. Submit a clearly labelled test application.
5. Confirm the row is present using the CLI export command.
6. Test a correction and verify a `data_corrected` audit event exists.
7. Test deletion on the test record and verify personal data is gone.
8. Confirm direct HTTP access to `/members/data/` is denied.
9. Confirm `/privacy/` is accessible from the form.
10. Confirm no SQLite database or exported member data appears in Git status.

## Security notes

- Membership administration is not exposed publicly in 0.6.
- State-changing form submissions use a session CSRF token.
- A honeypot field provides basic automated-form filtering.
- Application errors are logged without deliberately logging submitted personal fields.
- Health information is optional and requested only for safety/participation purposes.
- Photography/publicity consent is separate from required membership administration data.
