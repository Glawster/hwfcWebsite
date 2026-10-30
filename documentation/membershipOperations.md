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

On desktop, Member Details places Membership ID beside the three name fields. Date Of Birth occupies the first column on its own row. Home Address starts the next row and spans three columns with Postcode in the fourth. Email starts a separate contact row followed by Telephone and Preferred Contact. The grid adapts to two columns on tablet and one on mobile, with Home Address spanning the available width.

Payment Details places Last Payment, Paid Through and Next Payment Due together on a separate desktop row below Payment Plan and Payment Status. These fields wrap to the available columns on smaller screens.

History is scoped to the application UUID, not a search across people with matching names or email addresses. Recorded lapse and return events appear together when they belong to that same application. Separate applications are not automatically linked, and missing historical events cannot be reconstructed. No audit events produces an explicit empty-history message. Audit details are displayed as escaped text in From, To and Who columns: previous values go under From, new values and other recorded context under To, and the recorded actor under Who. Missing values display a dash; historical actor labels are shown as recorded.

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

## Member import

`/admin/memberImport.php` imports existing members from the "Hillsborough Walking Football Club – Member Responses" Google Form under the [REQ-004](../project/requirements/features/004-membershipApplicationAndManagement.md) import rules. Download the responses sheet from Google Sheets as Microsoft Excel (`.xlsx`) or as CSV for the Form Responses sheet. The column headings must match the form; a file with missing headings is rejected before preview.

Uploading shows a preview and saves nothing. Each row is marked:

| Result | Meaning |
| --- | --- |
| New | Saved as a `Pending` application when the import is confirmed. |
| Update | Update mode only: the row's Membership ID matches a member and at least one value differs. The changed fields are listed with old and new values; health information shows only that it changed. |
| Unchanged | Update mode only: the row matches a member and nothing differs. |
| Duplicate | Matches an existing member, or an earlier row, by first and last name plus date of birth or email address, or by Membership ID when update mode is off. Skipped; the existing record is not changed. |
| Invalid | Missing or invalid required details, such as an invalid date of birth or one that makes the member under 16. Skipped; correct the spreadsheet and import again. |

Every New and Update row has a tick box, ticked by default. Check All and Uncheck All select or clear them all, and only ticked rows are applied. Duplicates are checked again when the import is confirmed. Each imported record gets a `member_imported` audit event with the file name and spreadsheet row. Re-importing the same spreadsheet is safe: rows already imported appear as duplicates.

A missing date of birth does not block the import: the member is saved with `01/01/1970` as a placeholder, the preview shows a warning, and Notes records that the date must be amended. Correct it on the member record once known.

Imported members remain `Pending`. Accept each one from their member record to allocate a Membership ID.

Mapping from the form:

- "Full name" is split at the last space: the final word becomes the last name. Check multi-word surnames after import and correct them if needed.
- Names written with a capital after a leading O, such as `OHara` or `ONeill`, are imported as `O'Hara` and `O'Neill`. This applies to member and emergency-contact names. Other spellings, such as `Ohara`, are left unchanged.
- Email addresses are saved in lowercase with any spaces removed.
- An optional "Membership start date" column (`dd/mm/yyyy`) sets each member's start date; the club adds it to the sheet. Rows without one use the optional default start date on the upload form. Membership expiry is set to one year less a day after the start date, matching Date Details. Accepting the member keeps these dates; payment plan and status are still set on the member record.
- Medical, allergy, emergency-medication, mobility and other support answers are combined into the member's health information. "No", "None" and "N/A" style answers are ignored.
- Photography/publicity consent is recorded only for "Yes"; "Please ask me first" is stored as no consent, and the original answer is kept in Notes.
- Membership type, club news, WhatsApp group and volunteering answers are added to Notes because the member record has no fields for them yet.
- The form has no relationship field for the second emergency contact, so it is left blank unless the sheet has a "Second emergency contact relationship" column, as the export does.
- A "Membership expiry date" column overrides the calculated expiry. Payment columns are ignored for new members, who are Pending; set payment details after acceptance.
- A row with a Membership ID that is not in the register is Invalid. Leave Membership ID blank to add a new member.
- The privacy-notice version is recorded as `Member Responses Google Form`.

### Member export

Export CSV on the same page downloads every `Pending`, `Active` and `Lapsed` member in the same column layout as the Google Form, followed by Membership start date, Membership ID, Status, Membership expiry date, Payment plan, Payment status, Last payment date, Paid through date, Next payment due date, Second emergency contact relationship and Application reference. The file can be worked on offline and read by the import. Rejected applications are excluded.

- Health information is split back into the form's questions using the labels the import created. Health text from the website application form has no labels, so it is placed under the medical-condition question.
- Membership type, club news, WhatsApp, volunteering and the original photo answer are read back from the import lines in Notes. They are blank for members who did not come from the Google Form.
- The four confirmation columns and the trailing "Email Address" column are left blank because the website does not hold those answers separately.
- Values that a spreadsheet would treat as a formula are prefixed with an apostrophe; the import removes it again.

The file name defaults to `yyyymmdd HWFC Member Export.csv` using today's date and can be changed before downloading; `.csv` is added automatically.

### Updating members from an edited export

Tick "Update existing members matched by Membership ID" when uploading an edited export. Rows with a Membership ID are compared with that member in export format, so an unedited export shows every member as Unchanged.

- Blank cells leave the stored value unchanged; clear values on the member record instead.
- Membership ID, Status, Timestamp, Application reference, Next payment due date, the confirmation columns and the Notes answers (membership type, club news, WhatsApp, volunteering) are not changed by import. A changed Status is reported as a warning; accept, reject or undo from the member record.
- Changing the start date without changing the expiry date moves expiry to one year less a day after the new start, as on Date Details.
- Payment plan (`Not Set`, `Annual`, `Monthly`), payment status, last payment date and paid-through date can be changed only for `Active` members, using the same values as the Payment Details tab. Next payment due is recalculated as the day after paid-through.
- Health columns are compared together. If they differ, the member's health information is rebuilt from the row.
- When applying, a member edited on the website after the preview is skipped and reported; upload again to review the latest values.
- Each update records a `member_import_updated` History event listing the changed field names, file name and spreadsheet row. Values are not copied into the audit trail.
- Rows without a Membership ID are handled as new members, with the usual duplicate checks.

Open the CSV in a spreadsheet as text, or check telephone numbers and dates before saving: Excel and Google Sheets can drop the leading zero from telephone numbers that have no spaces.

The export contains health and emergency-contact details. Keep downloaded files on club devices only and delete them when finished.

### Member import and export retest

Run on staging (`HWFC_ENV=staging`, `HWFC_ADMIN_STAGING_MODE=1`) against a staging copy of the member database, never the production database.

1. Confirm the admin header shows the deployed version and Admin Tools links to Member Import.
2. Upload the member responses `.xlsx` and confirm the preview counts, that rows needing correction are marked Invalid with a reason, and that no health details appear.
3. Cancel and confirm no member records were added.
4. Upload again, confirm the import, and spot-check imported members: names, Pending status, health information, Notes and the `member_imported` History event.
5. Upload the same file again and confirm every imported row is a Duplicate and the import button is not offered.
6. Export CSV, open it in a spreadsheet, and confirm the columns match the Google Form and rejected applications are absent. Delete the downloaded file afterwards.
7. Upload the exported CSV and confirm all rows are Duplicates.
8. Accept two imported members, export, change one member's address and payment plan, and the other's address. Upload with update mode ticked and confirm both appear as Update with old and new values, and the rest as Unchanged.
9. Use Uncheck All, tick only the first member, apply, and confirm only that member changed and their History shows `member_import_updated` with field names only.
10. Confirm the export file name defaults to today's `yyyymmdd HWFC Member Export.csv` and that a typed name is used instead.

The preview omits health details. Uploaded files are not kept; the parsed preview is held in the administrator's server-side session until the import is confirmed or cancelled.

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
