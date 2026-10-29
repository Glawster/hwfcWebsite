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

## Member administration tabs

The member record at `/admin/member.php?id=<application UUID>` groups administration into tabs under [REQ-006](../project/requirements/features/006-adminPage.md). Membership and data-protection rules remain defined by [REQ-004](../project/requirements/features/004-membershipApplicationAndManagement.md).

| Tab | Purpose |
| --- | --- |
| Member Details | Identity, contact information and application actions. |
| Date Details | Membership dates and renewal information. |
| Payment Details | Membership payment administration and recorded payments. |
| Session Details | Attendance availability and a link to Session Takings. |
| Notes | Internal administration notes. Saving or validation errors return to this tab. |
| History | All stored audit events for this application, newest first, with timestamps and recorded details. |
| Data Protection | Privacy notice, photography/publicity consent and the home for subject-access, correction, retention and erasure workflows. |

The selected tab uses club red, with a darker red interaction state. Update Consent uses the same red primary-button style as other save actions, and returns to Data Protection after saving. Session Takings displays the attendance-report filename as ordinary text within its help paragraph.

History is scoped to the application UUID, not a search across people with matching names or email addresses. Recorded lapse and return events appear together when they belong to that same application. Separate applications are not automatically linked, and missing historical events cannot be reconstructed. No audit events produces an explicit empty-history message. Audit details are displayed as escaped text.

Undo Acceptance returns a mistakenly accepted application to Pending and releases its allocated Membership ID for reuse, with an audit record. A member lapsing or leaving does not release their ID.

### Member-page retest

Use test records and the same website build containing these changes.

1. Open each member tab and confirm the selected tab is red and the URL selects the corresponding section.
2. Open Notes, change and save a note, and confirm Notes remains selected and the saved text appears. Confirm notes no longer appear in Member Details.
3. Open Data Protection and confirm the privacy-notice link, consent controls and subject-access, correction and retention/erasure descriptions are present. Confirm those GDPR actions are clearly described as CLI-only.
4. Change photography/publicity consent on a test record. Confirm the red Update Consent button saves the choice and returns to Data Protection. Confirm consent controls no longer appear in Member Details.
5. Open History and confirm the note and consent audit events appear, newest first, with their recorded details. Check a second member to confirm events are isolated. A record with no events must show the empty-history message.
6. Where recorded lapse and return events exist for the same application, confirm both appear in History. This tab does not create lapse/return events or merge separate applications.
7. Open Session Takings and confirm the attendance-report filename has the same font and size as its surrounding help text.
8. Repeat the tab checks at a narrow mobile width; confirm tabs remain reachable and history tables scroll within their container.

## GDPR / data-subject operations

The initial GDPR operations are deliberately CLI-only until authenticated administration is implemented.

The member record's Data Protection tab groups privacy-notice information and photography/publicity consent controls, and identifies the availability of subject-access, correction and retention/erasure workflows under [REQ-006](../project/requirements/features/006-adminPage.md). Export, personal-data correction and deletion continue to use the protected CLI tooling below; browser controls for those operations are not yet implemented.

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
