# 006: Administration page

## Status

InProgress

## Outcome

As a `club administrator`, I need a single private administration area so that I can manage membership, kit orders and other club administration functions from one consistent place.

## Context

HWFC already has requirements for administrator authentication (REQ-003), kit-order tracking (REQ-002) and membership application/management (REQ-004). This requirement defines the shared `/admin/` user interface that brings those functions together without duplicating their underlying business rules or data stores.

The administration area must not be linked from the public website navigation. During development, a local-only read-only mode may be used so the interface can be tested against test data before authentication is complete. That development mode must not permit remote or production access.

## Scope

- Provide a private administration entry point at `/admin/`.
- Use the existing website visual language while clearly distinguishing administration pages from public pages.
- Provide an administration dashboard that can grow to include:
  - membership applications and member records;
  - kit orders and fulfilment status;
  - membership payment/expiry administration;
  - configuration functions already defined by related requirements.
- Use REQ-003 as the authentication boundary before any production administrator data is exposed.
- Do not add an Admin link to the public navigation.
- Reuse the authoritative membership database defined by REQ-004 rather than creating a separate admin copy of membership data.
- Reuse the authoritative kit-order records/configuration defined by the kit requirements rather than creating a separate admin copy.
- Start with a read-only membership-application view so the data presentation can be validated before state-changing workflows are added.
- Do not show health information, emergency-contact information or other particularly sensitive fields in list views unless operationally necessary.
- Require explicit navigation to a protected record-detail view before sensitive information is shown.
- Protect every state-changing administration action with authentication, authorisation and CSRF controls.
- Record material administrator changes through the audit mechanisms required by the underlying feature.
- Provide a Data Protection tab on each member record housing privacy-notice and consent information and the entry point for REQ-004 subject-access, correction, retention and erasure workflows. Clearly identify workflows that remain available only through protected command-line tooling.
- House internal member notes in a separate Notes tab and return there after a save or validation error.
- Provide a History tab on each member record showing all stored audit events for that application, newest first, with timestamps, event names and recorded details, including any recorded lapse and return events. State when no history is recorded; do not infer missing historical events.
- Provide an Undo Acceptance action under the reversal rules in [REQ-004](004-membershipApplicationAndManagement.md), including an explanation that the allocated Membership ID is released for reuse.
- Provide a Member Import/Export page implementing the [REQ-004](004-membershipApplicationAndManagement.md) spreadsheet import for the club's member responses Google Form: preview before saving, report duplicate and invalid rows, and add only new members as `Pending` applications. Provide a CSV export of the member register in the same layout, plus membership, payment and reference columns, so it can be worked on offline and re-imported. Offer an opt-in update mode that matches rows by Membership ID, previews changed fields and applies only the rows the administrator selects.
- Support responsive use on a laptop, tablet and mobile device where practical.

## Development-only local mode

- A temporary development mode may allow `/admin/` to be viewed without REQ-003 authentication only when both conditions are true:
  - an explicit local-development environment flag is enabled; and
  - the request originates from the loopback interface (`127.0.0.1` or `::1`).
- Local-development mode must be read-only.
- If either condition is false while authentication is not yet implemented, the administration area must not expose private data.
- Development mode must not be enabled by repository defaults or committed secrets.

## Out of scope for the first 0.6a2 slice

- Production administrator login/SMS delivery; this remains REQ-003.
- Editing, accepting or rejecting membership applications.
- Member payment/expiry workflows.
- Full kit fulfilment state transitions.
- Spreadsheet import/export UI.
- GDPR deletion/export UI; existing protected CLI tooling may continue to be used until an authenticated UI is implemented.

## Acceptance criteria

1. Given a local development server with the explicit development flag enabled, when a request to `/admin/` originates from loopback, then the administrator dashboard can be viewed.
2. Given the development flag is absent or false, when an unauthenticated request is made before REQ-003 is implemented, then membership/kit private data is not exposed.
3. Given a request not originating from loopback, when development mode is enabled, then local-development bypass is refused.
4. Given the membership database contains applications, when the local read-only dashboard is opened, then a summary/list of applications is read from the same database used by the public membership form.
5. Given an application list, when it is rendered, then it does not include health details or emergency-contact details in the list view.
6. Given the public website, when its navigation is inspected, then no Admin link is present.
7. Given future authenticated administration actions, when they mutate membership or kit data, then they must use the underlying feature's authoritative store and audit controls rather than maintaining a duplicate store.
8. Given an accepted application, when an administrator uses Undo Acceptance, then the interface returns the application to `Pending` and confirms the Membership ID release according to REQ-004's audited acceptance-reversal rules.
9. Given a member with recorded audit events, when the History tab is opened, then all audit events for that application are shown newest first with their timestamps and recorded details, and events for other applications are excluded. A record without audit events shows an empty-history message.
10. Given a member record, when Notes is opened and saved, then notes are maintained in that tab and the response returns to Notes.
11. Given a member record, when Data Protection is opened, then privacy-notice information, consent controls and the availability of subject-access, correction and retention/erasure workflows are shown. Updating consent returns to Data Protection.
12. Given a member responses spreadsheet, when it is uploaded to Member Import/Export, then a preview identifies new, duplicate and invalid rows without saving anything or showing health details; confirming saves only the new rows as `Pending` applications with audit events and never changes an existing member.
13. Given member records, when an administrator exports from Member Import/Export, then a CSV with the Google Form's columns is downloaded containing every non-rejected member, and importing that file into an empty register recreates the same member details.
14. Given an edited export, when it is uploaded with update mode, then rows matched by Membership ID show their changed fields (health shown only as changed) and unedited rows show as unchanged. Applying changes only the selected rows, skips members edited since the preview, never clears values from blank cells and records the changed field names in History.

## Dependencies and decisions

- Depends on REQ-003 Admin Authentication for production access.
- Depends on REQ-004 Membership Application and Management for membership data.
- REQ-002 Kit Order Tracking will use this interface for kit fulfilment administration.
- `0.6a2` is the first administration-interface increment within the wider 0.6 membership/admin development line.

## Verification

- Local HTTP test with and without `HWFC_ADMIN_DEV_MODE=1`.
- Local HTTP test proving non-loopback access cannot use the development bypass.
- Test database containing at least one membership application.
- Verify list data comes from `HWFC_MEMBER_DB`.
- Verify no health/emergency-contact details appear in the dashboard list.
- Review public navigation to ensure `/admin/` is not linked.
- Verify Undo Acceptance returns the application to `Pending`, confirms the ID release, and uses REQ-004's audit and ID-reuse rules.
- Verify History ordering, isolation between member records, escaped audit details and the empty-history message.
- `tests/test_memberImportHttp.py` covers XLSX and CSV preview, commit, duplicate and invalid rows, re-import, cancellation, template mismatch, CSRF, the CSV export round trip, update mode, row selection, stale previews and the export file name.
- Follow the [member-page retest checklist](../../../documentation/membershipOperations.md#member-page-retest), including Notes, Data Protection, consent-save navigation and responsive tab use.

## Change history

- 2026-10-30: updated — keep member responses form answers in a Form Answers block on Member Details, separate from Notes, and include them in export and update mode.
- 2026-10-30: updated — rename the Member Import page to Member Import/Export.
- 2026-10-30: updated — add opt-in update mode matched by Membership ID with row selection, membership and payment columns in the export, and a nameable export file.
- 2026-10-30: updated — add a CSV export in the member responses layout to Member Import for offline work.
- 2026-10-30: updated — add a Member Import page for the member responses Google Form, implementing REQ-004's preview-first, non-destructive spreadsheet import.
- 2026-10-29: updated — add a Data Protection tab housing privacy and consent controls and identifying the current availability of GDPR workflows.
- 2026-10-29: updated — add a member History tab for the complete stored application audit trail, including recorded lapse and return events.
- 2026-10-29: updated — document Undo Acceptance and link the Membership ID release behaviour to REQ-004; the first read-only slice remains historical scope.
- 2026-10-28: created — define the shared private administration page and a safe local-only read-only development mode for the first `0.6a2` slice.
