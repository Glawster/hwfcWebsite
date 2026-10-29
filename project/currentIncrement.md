# Current increment

## 006: Administration page — member-tab changes awaiting retest

Branch: `feature/006-admin-page`

[Requirement](requirements/features/006-adminPage.md) · [Operations and retest checklist](../documentation/membershipOperations.md#member-page-retest)

The current local changes add separate Notes, History and Data Protection tabs, move consent controls to Data Protection, return note/consent saves to their respective tabs, use club red for the active tab and consent button, and remove inline-code styling from the Session Takings attendance-report help text. History shows stored events for the selected application only; GDPR export, correction and deletion remain CLI-only.

PHP syntax and diff checks passed. Temporary SQLite rendering checks passed for History ordering, member isolation, escaped details, the empty state, and Data Protection tab selection and consent placement. Browser retesting and deployment remain outstanding.

The newer remote admin implementation and matching database/payment code have been integrated into `feature/006-admin-page`. Browser retesting remains outstanding.

## Previous membership foundation increment

## 004: Membership application and management — 0.6a1

[Requirement](requirements/features/004-membershipApplicationAndManagement.md)

Branch: `feature/004-membership-application-and-management`

Version: `0.6a1`

The 0.6 increment introduces the public `/members/` section and the first database-backed membership workflow. It includes a new membership application, a link for existing members to update their details, secure server-side validation and storage, privacy-notice version recording, separate photography/publicity consent, relevant health/safety information, and GDPR-supporting CLI export/correction/deletion operations. See [membership operations](../documentation/membershipOperations.md).

The application database is SQLite-backed. Production deployment must set `HWFC_MEMBER_DB` to a protected path outside the public document root. A protected local fallback exists under `members/data/` for development only.

### Deliberately deferred to 0.7

The browser administration page is not part of 0.6. Once membership data exists and the operational workflow can be evaluated, 0.7 will implement the authenticated Members administration interface. REQ-003 Admin Authentication must protect that private interface before member data is exposed through the browser.

Later REQ-004 work also includes application approval/rejection, permanent Membership IDs, membership periods and expiry, payment tracking, import/export, contacts export and virtual membership cards.

### Verification still required

Before 0.6 is deployed for real applications:

- run PHP syntax checks on all new membership PHP files;
- confirm PDO SQLite is enabled on the cPanel host;
- configure `HWFC_MEMBER_DB` outside the web root;
- submit a clearly labelled test membership application;
- verify subject-access export, audited correction and confirmed deletion against the test record;
- verify `/members/data/`, `/members/lib/` and `/members/bin/` cannot be accessed over HTTP;
- verify no database file or exported personal data is tracked by Git;
- review the production retention process against the published privacy policy.

REQ-004 remains `InProgress`/partially implemented until the full requirement is delivered; this increment intentionally delivers only the public/data foundation needed before the 0.7 administration work.

## Integration with main

Main's kit-order fixes and requirement statuses have been integrated while retaining the administration branch's shared PHP pages and member layouts. Requirements 001 and 005 are Completed; 004 remains InProgress. The incoming kit-window requirement was renumbered to 008 because 006 already identifies the Administration Page. Requirement 007 now references 008; the next available requirement number is 009.

Merge verification: PHP syntax checks passed for 35 admin, kit, membership and public-page files. History and Data Protection SQLite rendering checks passed. The HTTP suite could not start because this session blocks local sockets (`PermissionError`); PHPUnit was not available in this environment. Browser/HTTP retesting remains required.
