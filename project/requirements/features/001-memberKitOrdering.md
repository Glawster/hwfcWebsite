# 001: Member kit ordering

## Status

InProgress

## Outcome

As an HWFC member, I need to order approved club kit during a defined ordering window and pay by bank transfer so that the kit manager receives a complete, trackable order that can be consolidated with the current batch and placed with the supplier.

## Context

HWFC currently needs a simple replacement for the paper kit-order process. The supplied paper form captures member identity, date, garment, initials and size, club-payment status, and receipt/signature information.

Kit orders will be accepted in defined ordering windows rather than through an always-open shop. Each window has an opening date and closing date and groups submitted orders into a batch.

Whenever a valid order is submitted, the complete order summary must be emailed immediately to the kit manager. The kit manager email address will be stored in server-side configuration and must not be hard-coded into source-controlled page or PHP files.

Each HWFC member is entitled to one free club shirt when joining. The order form therefore needs an explicit new-member checkpoint so that a new member can claim that entitlement and so the kit manager can see and track the claim. Until HWFC introduces a member register, the kit manager is responsible for retaining the knowledge of which members have already used this entitlement.

The website implementation should remain consistent with the existing lightweight HTML/CSS/PHP site. Payment will be by bank transfer. The supplier applies initials to garments where personalisation is offered. Shirt-number personalisation is not part of this requirement.

Detailed page copy and layout intent are maintained in `../../../documentation/kitOrderPageCopyAndWireframe.md`.

## Scope

- Add a permanent `/kit/` page for existing members.
- Support a configured kit-order window with batch identifier, opening date/time and closing date/time.
- Accept new orders only while the configured kit-order window is open.
- Clearly display the current order closing date while the window is open.
- Display an appropriate not-yet-open or closed message outside the active window and prevent submission.
- Present configurable kit products with product name, description, price, available sizes and optional image.
- Allow one order to contain one or more products.
- Capture size and quantity per order line.
- Capture initials per order line only for products configured to support supplier-applied initials.
- Capture whether the member is a new member claiming their one free club shirt.
- Apply the free-shirt entitlement to at most one configured free-shirt-eligible garment unit on the order.
- Clearly identify the free-shirt claim in the member review, member confirmation and kit-manager notification.
- Capture the member's Full Name, email address and mobile/contact telephone number.
- Optionally capture section, order notes and payer/account name when a transfer may arrive under a different name.
- Support club collection or delivery.
- Capture delivery address only when delivery is selected.
- Calculate and display the order total before submission.
- Recalculate the authoritative total on the server from server-controlled product configuration and entitlement rules.
- Generate a unique order reference suitable for use as the bank-transfer reference.
- Associate every accepted order with the active kit-order batch/window.
- Display bank-transfer instructions after successful submission.
- Email a complete order summary to the configured kit manager immediately after a valid order is accepted.
- Provide the member with a complete confirmation on screen and, where mail delivery is available, by email.
- Keep enough order information to support batch tracking and supplier ordering for the current window.
- Apply server-side validation, the existing honeypot approach and a required `I am human` confirmation.
- Keep live bank destination details and the kit manager email address out of source control and load them from server-side deployment configuration.
- Keep the order flow practical on a mobile phone.

### Kit-order window

Each ordering window must be configured with:

- batch/window identifier;
- opening date/time;
- closing date/time; and
- optional display name, for example `Autumn 2026 Kit Order`.

The server is authoritative for deciding whether an order can be accepted.

Expected behaviour:

```text
Before opening  -> show when ordering opens; do not accept orders
Open            -> show closing date/time and accept valid orders
After closing   -> show that the window is closed; do not accept orders
```

The closing date must be clearly visible so members understand that late orders may need to wait for the next ordering window.

### New-member free-shirt entitlement

Each member is entitled to one free club shirt when joining.

The form must therefore include a clear checkpoint such as:

```text
[ ] I am a new member and I am claiming my one free club shirt
```

Rules:

- The claim is optional and must not be inferred automatically.
- Only products explicitly configured as `free-shirt eligible` may receive the entitlement.
- The entitlement applies to at most one garment unit on an order.
- If more than one eligible shirt is ordered, only one unit is free and additional units remain chargeable at the configured price.
- The base price of the qualifying shirt is reduced to £0.00 in the authoritative server-side total.
- Personalisation charges and delivery charges remain payable unless separately configured otherwise.
- If the member claims the entitlement without selecting an eligible shirt, the order must not be accepted until corrected.
- The order review and confirmation must identify which shirt received the free entitlement.
- The kit-manager notification must clearly state that the member has claimed the new-member free shirt so the entitlement can be checked and tracked administratively.

REQ-001 does not introduce a member account or membership register. For the initial implementation, the website records the claim and the kit manager is the authoritative manual check for whether the member is eligible and whether their free-shirt entitlement has already been used. The kit manager must retain this knowledge outside the website.

A future member register may record and expose entitlement status so that this verification can be automated. That would be delivered under a separate requirement; it must preserve the business rule that each member receives only one free joining shirt.

### Order-line data

Each selected item must carry its own:

- product identifier;
- product name;
- size;
- quantity;
- unit price;
- line total;
- whether one unit on that line is the new-member free shirt, where applicable; and
- initials, only where that product supports supplier-applied personalisation.

The supplier, not HWFC, applies the initials. The website must capture the exact initials required and pass them through clearly with the supplier order.

### Payment

Payment method is bank transfer only.

The website may capture or generate:

- order reference;
- order total; and
- optional payer/account name for payment matching.

The website must not request or store the member's bank account number, sort code, card number, card security code, online-banking credentials or banking authentication codes.

Recommended payment-reference format:

```text
KIT-YYYYMMDD-NNNN
```

An alternative collision-resistant format is acceptable if it remains practical as a bank-transfer reference.

### Fulfilment

The member selects either:

- club collection; or
- delivery.

Delivery may require recipient name, address lines, town/city, optional county and postcode. Any delivery charge must be configured and displayed before submission.

### Kit manager notification

Whenever a valid order is accepted, the system must send a complete order summary to the configured kit manager email address.

The kit manager email address must be loaded from server-side configuration and must not be committed as a live operational value in source control.

The email must contain enough information for the kit manager to place and track the order without referring back to the website form:

- batch/window identifier and display name where configured;
- order reference;
- submission date/time;
- member name;
- member email address;
- member telephone number;
- whether the member claimed the new-member free shirt;
- which garment/unit received the free-shirt entitlement, where applicable;
- every garment ordered;
- size per order line;
- quantity per order line;
- initials where applicable;
- unit price and line total;
- order total;
- collection or delivery method;
- delivery details where applicable;
- payer/account name where supplied;
- order notes;
- initial payment status `Payment Pending`.

The email subject should make the order easy to identify, for example:

```text
HWFC Kit Order KIT-YYYYMMDD-NNNN – Member Name
```

A mail failure must not silently discard an order or falsely report successful notification. The implementation must define a recoverable failure path so the order can be retried or otherwise brought to the club's attention.

### Order status model

The business process should be capable of representing:

```text
Submitted
Payment Pending
Paid
Ordered
Ready for Collection / Dispatched
Complete
Cancelled
```

A member-facing status portal is not required by this requirement.

## Out of scope

- Member register and automated membership verification.
- Automated verification of the free-shirt entitlement.
- Shirt-number personalisation.
- Card payments.
- PayPal or other payment gateways.
- Direct debit.
- Website member accounts or passwords.
- Automatic access to the club bank account.
- Automatic confirmation that a transfer has arrived.
- Persistent inventory or stock management.
- Supplier API integration.
- Automated courier/dispatch integration.
- Online refunds.
- Member-facing order-status lookup.
- Full administrator dashboard.

## Acceptance criteria

1. Given a member visits `/kit/`, when the page loads during an open kit window, then the available configurable kit products, prices, size choices and closing date are presented using the existing HWFC visual language.
2. Given the current time is before the configured opening time or after the configured closing time, when a member visits or submits `/kit/`, then the server does not accept a new order and the member is shown the appropriate window status.
3. Given a member selects one or more products, when they build an order, then size and quantity are recorded separately for each order line.
4. Given a product supports supplier-applied initials, when the member selects it, then initials can be entered for that order line; and given a product does not support initials, no initials field is accepted for it.
5. Given any product is ordered, when the form is reviewed or submitted, then no shirt-number field is present.
6. Given a member is new and wishes to claim their free shirt, when completing the form, then they can explicitly select the new-member free-shirt checkpoint.
7. Given the free-shirt checkpoint is selected, when the order is validated, then exactly one unit of a configured free-shirt-eligible product may receive the entitlement.
8. Given the free-shirt checkpoint is selected but no eligible shirt is ordered, when the order is submitted, then the order is rejected with a clear validation message.
9. Given more than one eligible shirt is ordered and the entitlement is claimed, when the server calculates the total, then only one shirt unit has its base price reduced to £0.00 and all other units remain chargeable.
10. Given a free-shirt entitlement is applied, when the order is reviewed, confirmed or emailed to the kit manager, then the qualifying garment and the fact that this is a new-member entitlement are clearly identified.
11. Given an order is prepared, when the member enters their details, then Full Name, valid email address and telephone number are required.
12. Given collection is selected, when the order is submitted, then no postal address is required.
13. Given delivery is selected, when the order is submitted, then the configured required delivery-address fields are validated.
14. Given product, entitlement or delivery values are posted by the browser, when the server processes the order, then product prices, allowed sizes, personalisation rules, free-shirt eligibility and charges are taken from server-controlled configuration rather than trusted from browser values.
15. Given a valid order, when the server calculates the total, then the total is calculated from configured product prices, the single free-shirt entitlement where applicable, configured personalisation charges and configured delivery charges using whole-pence arithmetic or an equivalently safe monetary representation.
16. Given a valid order is accepted, when processing succeeds, then a unique order reference is generated, the order is associated with the active kit window and the member is shown the amount due and bank-transfer instructions.
17. Given bank-transfer instructions are displayed, when the member reads them, then they are explicitly told to use the generated order reference as the payment reference.
18. Given the form is displayed or processed, when payment information is requested, then the member is never asked for their own account number, sort code, card details, online-banking credentials or authentication codes.
19. Given a valid order is accepted, when the kit-manager notification is generated, then a complete order summary is emailed to the kit manager address loaded from server-side configuration.
20. Given the kit-manager email is read, then the batch, order reference, member details, new-member entitlement claim, garments, sizes, quantities, initials, fulfilment details, totals, notes and initial payment status are sufficient to place and track the supplier order without consulting the original form submission.
21. Given a new-member free-shirt claim is received, when the kit manager processes the order, then the notification provides enough information for the kit manager to perform the current manual eligibility/previous-use check.
22. Given a valid order is submitted, when confirmation succeeds, then the member receives or can view a complete order summary, amount due, collection/delivery choice, bank-transfer details and payment reference.
23. Given kit-manager email delivery fails, when the server detects the failure, then the failure is not silently ignored and the member is not falsely told that all administrative processing completed successfully.
24. Given invalid input is submitted, when validation fails, then errors are shown in plain language and valid user-entered data is retained where safe and practical.
25. Given an automated or incomplete submission, when anti-bot controls fail, then the order is not accepted as a valid order.
26. Given the form is used on a common mobile screen size, when a member completes an order, then controls, labels, errors, review information and the submit action remain usable without requiring a desktop layout.
27. Given the repository is inspected, when configuration is reviewed, then no live bank account details, live kit-manager email address or other secrets are committed to Git.
28. Given the supplier needs to fulfil an order, when the kit-manager notification is read, then the selected garments, sizes, quantities and initials are sufficiently explicit to translate directly into a supplier order.

## Dependencies and decisions

- [ADR 001: Private kit-order records before notification](../../adr/001-privateKitOrderRecords.md).

- Existing lightweight site architecture: plain HTML/CSS with PHP form handling.
- Existing Contact form validation/mail pattern should be reused where suitable.
- Detailed UX/copy: `documentation/kitOrderPageCopyAndWireframe.md`.
- Kit-window values, bank details and kit-manager destination address are deployment/server configuration.
- Product configuration defines which garment is eligible for the one-free-shirt new-member entitlement.
- Until a member register is introduced, free-shirt entitlement verification is a manual responsibility of the kit manager.
- A future member-register requirement may replace that manual verification with an authoritative membership lookup.
- ADR 001 records the private persistence and notification recovery decision.

## Verification

- Review `/kit/` before, during and after a configured ordering window.
- Verify server-side rejection of submissions outside the window.
- Submit orders containing one and multiple products.
- Verify products with and without initials support.
- Verify no shirt-number field exists or can be accepted server-side.
- Verify new-member free-shirt claim with one eligible shirt.
- Verify a claimed entitlement cannot discount more than one shirt unit.
- Verify a claim with no eligible shirt is rejected.
- Verify additional shirts remain chargeable.
- Verify free-shirt status is shown in review, confirmation and kit-manager email.
- Verify the kit-manager email provides sufficient member identity information for the current manual entitlement check.
- Verify invalid sizes, quantities, initials, entitlement values and manipulated browser prices are rejected or ignored in favour of server configuration.
- Verify collection and delivery validation paths.
- Verify authoritative totals and configured charges.
- Verify generated order references are unique for test submissions.
- Verify accepted orders carry the configured batch/window identifier.
- Verify the complete order summary is emailed to a configured test kit-manager address.
- Verify the mail subject identifies the order clearly.
- Verify kit-manager email failure handling.
- Verify member confirmation contains the required information.
- Verify no member banking credentials are requested, stored or logged.
- Verify live club bank details and the live kit-manager email address are supplied from deployment configuration rather than committed source.
- Verify honeypot and human-confirmation handling.
- Test actual outbound mail on the production cPanel host before marking complete.

## Change history

- 2026-10-08: created from the proposed HWFC website kit-order workflow.
- 2026-10-08: bank transfer selected as the initial payment method.
- 2026-10-08: paper-order sketch incorporated; size, quantity and initials defined as order-line data.
- 2026-10-08: clarified that initials are applied by the supplier and shirt numbers are excluded.
- 2026-10-08: migrated to the shared OMP requirements-management structure.
- 2026-10-08: added configurable kit-order windows and closing dates.
- 2026-10-08: specified immediate complete-order email notification to the kit manager using a server-configured destination address.
- 2026-10-08: added explicit new-member checkpoint and one-free-shirt entitlement handling.
- 2026-10-08: clarified that entitlement history is retained manually by the kit manager until a future member register is introduced.
- 2026-10-08: implementation started; requirement moved to `InProgress`.
