# 004: Membership application and management

## Status

InProgress

## Outcome

As a `prospective member, member or club administrator`, I need a secure membership application and membership-management facility so that HWFC can receive applications, maintain an accurate member register, record membership payments and expiry, issue membership identification and manage personal information in a GDPR-compliant way.

## Context

HWFC currently needs a consistent way for prospective members to submit membership information and for authorised club administrators to maintain the resulting member register.

Membership information includes personal and contact details, date of birth and emergency-contact information. This is more sensitive than a simple club enquiry or kit order and therefore must be stored and managed as membership data rather than relying on email messages or spreadsheets as the long-term system of record.

The website administration area is also expected to provide member-management functions, including import of existing members and export of membership/contact data. Membership records will additionally support a unique Membership ID, membership payment history, membership start and expiry dates, and a club-branded virtual membership card. A member photograph may be added later or by the member/administrator where required, but a photograph must not be mandatory for initial membership.

Membership fees will be paid by bank transfer. The membership facility must reuse the club bank-transfer details already configured for the club shop rather than maintaining a second independent copy of the account name, sort code and account number.

The solution must support the club's data-protection obligations, including transparency, access, correction, retention and deletion or anonymisation where appropriate.

## Scope

- Provide a public membership application page separate from the kit-order form.
- Allow a prospective member to submit, at minimum:
  - first name;
  - last name;
  - date of birth;
  - postal address;
  - email address;
  - mobile or telephone number;
  - emergency-contact name;
  - emergency-contact telephone number; and
  - emergency-contact relationship to the applicant.
- Capture any membership section or category required by the club without attempting to infer it from the member's name.
- Validate required fields and reject clearly invalid submissions without creating a membership record.
- Present the current membership privacy information before submission.
- Record the privacy-notice version and the date/time associated with the submitted application so that the club can demonstrate what information was presented when the application was made.
- Avoid describing membership processing as a general requirement for the applicant to "consent to GDPR"; any genuinely optional consent must be presented separately from information required to administer membership.
- Store applications in the website's membership database rather than relying on email as the authoritative record.
- Assign each application a system identifier suitable for internal processing.
- Support membership states including at least `Pending`, `Active`, `Lapsed` and `Rejected`.
- Allow an authorised administrator to review a pending application and accept, reject or otherwise update it.
- Create or activate a member record when an application is accepted.
- Assign each accepted member a unique, permanent Membership ID.
- Make the Membership ID human-readable; an initial format such as `HWFC-000123` is acceptable, with the exact presentation kept configurable.
- Never reuse a Membership ID that has previously been issued, including where the member subsequently lapses or leaves the club.
- Display Membership ID in the administrator member record and relevant exports.
- Provide a private Members function within the administration area for authorised administrators.
- Allow authorised administrators to view, add, edit and maintain member records.
- Record the current membership period, including at least a membership start date and membership expiry date.
- Make membership expiry visible in the administrator member list and member record.
- Allow administrators to identify memberships that are current, approaching expiry and expired.
- Derive `Lapsed` or equivalent expired membership state from the applicable expiry policy rather than relying only on an administrator remembering to change the status manually.
- Support renewal by creating a new membership period while preserving previous periods for history.
- Record membership-fee payment separately from the member's identity and contact information.
- Maintain membership payment history rather than overwriting the previous payment when a member renews.
- For each membership payment, record at least:
  - membership period to which the payment applies;
  - expected fee amount;
  - amount received;
  - payment status;
  - date payment was received;
  - bank-transfer/payment reference where known;
  - optional payer/account name where useful for matching a bank transfer; and
  - administrator/date that confirmed or amended the payment record.
- Support payment states including at least `Unpaid`, `Pending`, `Paid` and `Waived`; any later refund/correction state must preserve the audit history.
- Do not store the member's bank account number, sort code, online-banking credentials, authentication codes or other banking secrets.
- Display the club bank-transfer instructions to the applicant/member using the same configured account name, sort code and account number used by the club shop.
- Reuse the bank configuration already exposed through the protected kit/club-shop configuration rather than duplicating club bank details in membership code or another configuration file.
- Give each membership payment a clear payment reference that can be matched to the member or membership period; the exact format may be configurable but should incorporate the Membership ID once one exists.
- Do not mark a member as paid merely because bank-transfer instructions were displayed; payment status must reflect an actual recorded/confirmed payment or authorised waiver.
- Allow an authorised administrator to record or confirm receipt of a membership bank transfer.
- Allow an authorised administrator to correct an incorrectly recorded membership payment without silently destroying the previous audit information.
- Allow existing member data to be imported from a spreadsheet with a preview before committing changes.
- Allow the import to include current membership status, membership start/expiry dates and payment state where these are present in the source data.
- Validate imported rows and report duplicate or invalid entries rather than silently overwriting existing members.
- Keep spreadsheet import non-destructive by default.
- Allow the member register to be exported in a spreadsheet-friendly format such as CSV/XLSX.
- Include appropriate membership status, payment status, membership start and expiry dates in administrative membership exports.
- Allow contact information to be exported in a contacts-friendly format such as vCard for use on a phone or with contact-based messaging workflows.
- For contact export, use the member's first name and last name and set the organisation/company to `HWFC` or `HWWFC` according to the member's applicable club section.
- Restrict membership records, payment records, emergency contacts, date of birth, address and other private membership information to authorised administrators and other explicitly authorised functions.
- Record sufficient administrative audit information to identify when a membership application was submitted and when material membership data, payment data or membership status was changed.
- Support locating the data held for an individual so that the club can respond to a subject access request.
- Support correction of inaccurate member information.
- Support deletion or anonymisation of membership data where required, while retaining only information the club has a lawful need to retain.
- Support a configurable retention approach so that personal data for former or rejected members is not retained indefinitely without a defined reason.
- Treat emergency-contact information as personal information and expose it only where there is an operational need.
- Provide each active member with a club-branded virtual membership card.
- Use the existing club visual scheme and logo for the virtual membership card.
- Show at least the following on the virtual membership card:
  - member name;
  - Membership ID;
  - relevant membership section/category;
  - current membership status; and
  - membership expiry date.
- The virtual card must not represent membership as current after the recorded membership expiry date unless a valid renewal/current period exists.
- Support an optional member photograph on the virtual membership card.
- Ensure the virtual card has a usable layout when no photograph is present.
- Store and process a member photograph only when the feature is used and there is a defined club purpose for doing so.
- Allow the virtual membership card to be viewed comfortably on a mobile telephone.
- Allow a virtual membership card to be regenerated when relevant member information changes.
- Ensure only a currently active, unexpired membership record can produce a valid active membership card.

## Out of scope

- Online card payment or direct-debit processing in the initial implementation; membership fees are paid by bank transfer.
- Automatic reconciliation against the club's bank account or open-banking feed in the initial implementation.
- Apple Wallet, Google Wallet or other native wallet-pass integration in the initial implementation.
- A mandatory member photograph.
- Facial recognition or automated identification from member photographs.
- Inferring a member's sex, membership section or other personal attribute from their name or photograph.
- Public access to the member register.
- Public display of date of birth, postal address, telephone number or emergency-contact details.
- Using email messages as the permanent membership database.
- Replacing the separate kit-order workflow defined by REQ-001 and REQ-002.
- Hard-coding a fixed membership fee, renewal date or membership duration before the club decides those policies; these must be configurable.

## Acceptance criteria

1. Given a prospective member, when they open the membership application page, then they can enter the required identity, address, contact, date-of-birth and emergency-contact information.
2. Given a membership application with missing required information or invalid field formats, when it is submitted, then the application is rejected with usable validation feedback and no valid membership record is created.
3. Given a valid membership application, when it is submitted, then the application is stored in the membership database with `Pending` status, an internal application identifier, submission date/time and the applicable privacy-notice version.
4. Given the membership application page, when privacy information is presented, then information required for membership administration is distinguished from any optional consent.
5. Given an authorised administrator, when they review a pending membership application, then they can accept or reject it without exposing the application to unauthenticated users.
6. Given a pending application that is accepted, when membership is activated, then a unique permanent Membership ID is assigned and the member becomes available in the administrator Members function.
7. Given two different accepted members, when their records are inspected, then their Membership IDs are different.
8. Given a member whose membership has lapsed or ended, when another member is later accepted, then the former member's Membership ID is not reused.
9. Given an authorised administrator, when they view the Members function, then they can view and maintain member records including membership status, Membership ID, current period, expiry and payment status.
10. Given an unauthorised visitor, when they attempt to access member, payment, date-of-birth, address or emergency-contact data, then access is denied.
11. Given a member required to pay a membership fee, when bank-transfer instructions are shown, then the displayed account name, sort code and account number are sourced from the same protected configuration used by the club shop.
12. Given the membership facility and club shop, when their bank-transfer configuration is inspected, then membership does not maintain an independent duplicate copy of the club bank details.
13. Given bank-transfer instructions displayed to a member, when no payment has yet been confirmed, then the membership payment remains `Unpaid` or `Pending` rather than being marked `Paid`.
14. Given an authorised administrator and a received bank transfer, when the administrator records the payment, then the applicable membership payment record stores the amount received, received date, payment reference where known and audit information, and its status becomes `Paid`.
15. Given a member who renews membership, when the renewal payment and new membership period are recorded, then the previous membership period and previous payment remain available as history.
16. Given an expired membership period with no current renewal, when membership status is evaluated, then the member is not represented as holding current active membership.
17. Given a membership expiry date, when an authorised administrator views the member list, then expired memberships and memberships approaching expiry can be identified.
18. Given a spreadsheet containing existing members, when an authorised administrator imports it, then the system previews the proposed import before committing it and identifies invalid or duplicate rows.
19. Given a spreadsheet import containing a duplicate or invalid row, when the import is committed, then existing member data is not silently overwritten by that row.
20. Given an authorised administrator, when they export the membership register, then a spreadsheet-friendly export is produced containing the permitted membership fields including Membership ID, membership dates and payment status.
21. Given an authorised administrator, when they request a contacts export, then a contacts-friendly export such as vCard is produced using first name, last name and `HWFC` or `HWWFC` as the organisation as applicable.
22. Given a request to identify information held about a member, when an authorised administrator searches the membership system, then the member's applicable stored membership and payment data can be located and exported or otherwise assembled for review.
23. Given inaccurate member information, when an authorised administrator corrects it, then the corrected values are stored and the material change is auditable.
24. Given an incorrectly recorded payment, when an authorised administrator corrects it, then the corrected payment information is available without silently erasing the fact that a material payment-record change occurred.
25. Given membership data that no longer has a defined retention need, when an authorised administrator carries out the applicable deletion or anonymisation action, then unnecessary personal data is no longer retained as active personal information.
26. Given an active, unexpired member, when their virtual membership card is displayed, then it uses club branding and shows the member name, Membership ID, membership section/category, active status and expiry date.
27. Given an active member without a photograph, when their virtual membership card is displayed, then the card remains complete and usable without a placeholder photograph being required.
28. Given an active member with an approved photograph, when their virtual membership card is displayed, then the photograph can be shown as part of the card without exposing the original membership record publicly.
29. Given a member whose relevant card information has changed, when the virtual card is regenerated or next displayed, then it reflects the current authorised membership information.
30. Given a member whose status is not `Active` or whose membership has expired, when their membership card is displayed or validated, then it does not represent that member as having current active membership.
31. Given a mobile telephone viewport, when an active member views the virtual card, then the card is readable without requiring a desktop layout.
32. Given a membership payment record, when it is inspected, then no member bank account number, member sort code, online-banking credentials or banking authentication secrets have been stored.

## Dependencies and decisions

- REQ-003 Admin Authentication is required before private member-management functions are made available in production.
- REQ-001 Member Kit Ordering and REQ-002 Kit Order Tracking may use the member register in future, but membership remains a separate source of truth from kit orders.
- Membership application and member records must use the database as the authoritative data store; spreadsheets are an import/export mechanism only.
- Membership ID is a permanent club identifier and is distinct from the internal database primary key and the application identifier.
- The exact Membership ID presentation should be configurable so the visible format can evolve without changing internal identity semantics.
- Membership fee amount, membership duration and renewal/expiry policy must be configurable rather than embedded in application code.
- Membership payments use bank transfer. The membership feature must reuse the club account name, sort code and account number from the protected club-shop configuration currently loaded through `kit/lib/kitConfig.php` / `config/kitConfig.json` (or a later shared configuration abstraction) rather than creating another source of bank details.
- A membership payment record represents the club's record that money was received or waived; displaying payment instructions does not constitute payment confirmation.
- Payment history and membership-period history must be retained so renewals do not overwrite previous years/periods.
- Member photographs are optional and should not be collected unless the club has a defined operational purpose for them.
- The initial virtual card is a responsive web-based card; native wallet integration can be considered as a later requirement.
- Data-protection notices, retention periods and lawful bases must reflect the club's approved privacy policy and must not be hard-coded in a way that prevents policy updates.

## Verification

- Form-validation tests covering every required membership application field.
- Integration test proving a valid submission creates a `Pending` application with privacy-notice version and submission timestamp.
- Tests for application acceptance, rejection and membership-status changes.
- Tests proving Membership IDs are unique, permanent and never reused.
- Tests for membership-period creation, renewal, expiry and preservation of historical periods.
- Tests proving expired members are not presented as currently active without a valid renewal period.
- Tests for membership payment states, payment confirmation, payment correction and payment history preservation.
- Configuration test proving membership bank-transfer instructions use the same protected bank configuration as the club shop and do not duplicate the bank details in membership configuration.
- Tests proving display of payment instructions alone never marks a payment as `Paid`.
- Tests proving member banking credentials/account details are never requested or stored.
- Authorisation tests proving unauthenticated or unauthorised users cannot access membership, payment or emergency-contact data.
- Import tests covering valid rows, invalid rows, duplicate members, membership dates/payment state, preview behaviour and non-destructive handling.
- Export tests for spreadsheet-friendly and vCard/contact output, including Membership ID, current membership dates/payment state and appropriate `HWFC`/`HWWFC` organisation value.
- Tests covering subject-access search/export support, correction and deletion/anonymisation workflows.
- Retention-policy review against the club's approved privacy notice before production release.
- Audit tests proving material membership and payment changes are attributable and timestamped without logging unnecessary sensitive values.
- Responsive UI tests for the virtual membership card on common mobile viewport sizes.
- Card tests with and without a member photograph.
- Tests proving non-active or expired members cannot be represented by the system as holding an active membership card.
- Security review covering storage, access control, CSRF protection, input validation and prevention of unintended public exposure of member data.

## Change history

- 2026-10-15: created — add online membership applications, member database administration, GDPR-supporting controls, permanent Membership IDs and a club-branded virtual membership card with optional photograph support.
- 2026-10-15: updated — add membership fee payment tracking, membership periods and expiry/renewal history, and require bank-transfer details to reuse the protected club-shop bank configuration.
- 2026-10-27: status ToDo -> InProgress — myBoard.
