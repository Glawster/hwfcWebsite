# Kit Order Testing Plan

Status: Draft for REQ-001 implementation  
Requirement: `../project/requirements/features/001-memberKitOrdering.md`

## 1. Purpose

This document defines the automated and production-path testing approach for the HWFC member kit-ordering workflow.

The suite should follow `testingProcess.md`: fast component tests should prove individual rules, while at least one integration path should exercise the real order-processing composition from submitted form data through validation, total calculation, notification construction and member confirmation.

## 2. Recommended Test Stack

### PHPUnit

Use PHPUnit for PHP unit, component and integration tests covering server-side behaviour.

Primary targets:

- kit-window open/closed logic;
- server-controlled product catalogue rules;
- size and quantity validation;
- initials validation;
- new-member free-shirt entitlement;
- delivery/collection validation;
- authoritative total calculation;
- bank-transfer reference generation;
- kit-manager email summary generation;
- configuration and failure handling.

The implementation should avoid placing all behaviour directly in a single form handler. Prefer small PHP modules/functions such as:

```text
kitConfig.php
kitOrder.php
kitValidation.php
kitMail.php
```

The web handler should primarily orchestrate those components.

### HTTP integration tests

Run the site using PHP's built-in development server and submit real HTTP requests to the actual kit-order endpoint.

These tests should verify that the HTML/PHP/configuration pieces work together and that server-side controls cannot be bypassed by manipulating browser-submitted values.

### Playwright

Use Playwright for browser-level tests of the member-facing workflow.

Cover at least:

- desktop and mobile viewport behaviour;
- kit-window messaging;
- product selection;
- conditional delivery fields;
- new-member free-shirt checkpoint;
- displayed totals and order review;
- validation messages;
- successful confirmation presentation.

### Mail test double / mail sink

Development and automated tests must not send live operational email.

Use either:

- an injectable mail function/test double; or
- a local/test mail sink.

Tests should inspect the generated subject, recipient and message content.

One real outbound-mail test on the production cPanel host remains required before REQ-001 can be marked complete.

## 3. Critical Production Path

The critical workflow is:

```text
Member opens /kit/
        ↓
kit window checked
        ↓
products loaded from server configuration
        ↓
member completes and submits form
        ↓
server validates member and order data
        ↓
free-shirt entitlement applied where claimed
        ↓
server calculates authoritative total
        ↓
unique order reference generated
        ↓
complete kit-manager summary generated
        ↓
kit-manager email sent
        ↓
member confirmation generated
```

At least one integration test must exercise this path using the real production components, substituting only the final external mail-delivery boundary where necessary.

## 4. Requirement-Level Test Matrix

| Production behaviour | Unit | Integration | UI | Production acceptance |
| --- | --- | --- | --- | --- |
| Kit window before/open/closed | ✓ | ✓ | ✓ | |
| Product catalogue loading | ✓ | ✓ | ✓ | |
| Product and size validation | ✓ | ✓ | | |
| Quantity validation | ✓ | ✓ | | |
| Initials allowed/disallowed | ✓ | ✓ | ✓ | |
| No shirt-number handling | ✓ | ✓ | ✓ | |
| New-member free-shirt claim | ✓ | ✓ | ✓ | |
| Only one free shirt applied | ✓ | ✓ | ✓ | |
| Invalid free-shirt claim rejected | ✓ | ✓ | ✓ | |
| Additional shirts remain chargeable | ✓ | ✓ | ✓ | |
| Collection path | ✓ | ✓ | ✓ | |
| Delivery-address path | ✓ | ✓ | ✓ | |
| Authoritative server total | ✓ | ✓ | ✓ | |
| Manipulated browser prices ignored | ✓ | ✓ | | |
| Order reference generated | ✓ | ✓ | ✓ | |
| Active batch/window attached to order | ✓ | ✓ | | |
| Bank-transfer instructions | ✓ | ✓ | ✓ | |
| Kit-manager recipient loaded from server config | ✓ | ✓ | | |
| Complete kit-manager email summary | ✓ | ✓ | | ✓ |
| Kit-manager mail failure handling | ✓ | ✓ | | |
| Member confirmation content | ✓ | ✓ | ✓ | |
| Honeypot / human check | ✓ | ✓ | ✓ | |
| Mobile layout | | | ✓ | |
| Real production outbound email | | | | ✓ |

## 5. Core Automated Cases

The suite should include, at minimum:

### Kit-order window

- before opening time: page reports not yet open and POST is rejected;
- exactly at opening boundary: orders are accepted;
- during the window: orders are accepted;
- exactly at closing boundary: behaviour matches the configured inclusive/exclusive rule;
- after closing time: page reports closed and POST is rejected;
- missing/invalid window configuration fails safely.

### Product/order lines

- one valid product;
- multiple valid products;
- valid size;
- invalid size;
- zero quantity;
- negative quantity;
- non-integer quantity;
- excessive quantity;
- initials accepted for an enabled product;
- initials rejected for a non-enabled product;
- invalid initials character/length handling;
- shirt-number data is ignored/rejected because the field is not supported.

### New-member free shirt

- no entitlement claimed: normal price applies;
- entitlement claimed with one eligible shirt: one base shirt price becomes £0.00;
- entitlement claimed with multiple eligible shirts: only one unit is free;
- additional eligible shirts remain chargeable;
- entitlement claimed without eligible shirt: validation fails;
- manipulated browser claim cannot make a non-eligible product free;
- personalisation/delivery charges remain payable unless configured otherwise;
- order summary and kit-manager email identify the claimed entitlement;
- kit-manager remains responsible for manually confirming that the entitlement has not previously been used.

### Fulfilment

- collection requires no postal address;
- delivery requires configured address fields;
- optional address fields remain optional;
- configured delivery charge is included in the server total;
- no delivery charge is added if none is configured.

### Totals and tampering

- authoritative total uses server-side prices;
- posted hidden/browser price changes do not alter the total;
- posted free-shirt eligibility changes do not alter server rules;
- line totals and final total use whole-pence arithmetic;
- multiple quantities calculate correctly.

### References and batch data

- reference has the expected valid format;
- repeated submissions produce unique references;
- accepted order includes active batch/window identifier;
- batch/window information appears in the kit-manager summary.

### Kit-manager email

- destination is read from server-side configuration;
- subject includes order reference and member name;
- message contains member contact details;
- message contains every garment, size, quantity and initials;
- message contains free-shirt claim where applicable;
- message contains fulfilment details;
- message contains total and initial `Payment Pending` status;
- message contains batch/window information;
- missing kit-manager configuration fails safely;
- mail-send failure follows the documented recoverable failure path.

### Member confirmation

- displays order reference;
- displays ordered items and entitlement status;
- displays total due;
- displays bank-transfer destination details from server configuration;
- clearly instructs use of the generated order reference as payment reference;
- does not expose member banking credentials because none are collected.

### Form/security behaviour

- missing required member name/email/telephone is rejected;
- invalid email is rejected;
- honeypot submission is rejected/discarded;
- missing human confirmation is rejected;
- free-text values are safely escaped when rendered;
- order data is not exposed in query-string URLs;
- live bank details and live kit-manager email are absent from committed source/configuration.

## 6. Browser Test Viewports

At minimum exercise:

```text
Mobile:  375 × 667
Mobile:  390 × 844
Tablet:  768 × 1024
Desktop: 1280 × 800
```

The exact dimensions are representative rather than contractual. Tests should assert usable layout and controls rather than pixel-perfect rendering.

## 7. Production Acceptance Checks

Before marking REQ-001 completed:

1. Configure a temporary/test kit-order window on the production cPanel host.
2. Configure a test kit-manager destination address on the server.
3. Submit a representative order through the real public PHP path.
4. Confirm the kit-manager email arrives with the complete order summary.
5. Confirm the member-facing confirmation contains the correct total, reference and bank-transfer instructions.
6. Exercise one free-shirt entitlement order and verify it is represented correctly.
7. Confirm a submission outside the configured window is rejected server-side.
8. Confirm no live operational values have been added to the repository.

## 8. Expected Suite Size

REQ-001 will likely require approximately 35–50 focused automated tests. Test count itself is not a completion target; coverage of the agreed behaviours and the real production path is what matters.

## 9. Completion Rule

REQ-001 is not complete merely because PHPUnit and Playwright are green. Completion also requires the production-path outbound-email test and verification that the server-side configuration behaves correctly on the actual cPanel host.

## Implemented local suite

Run `php /path/to/phpunit-10.phar -c phpunit.xml` and `HWFC_TEST_PHP=php python3 tests/test_kitHttp.py`. The HTTP suite includes Playwright; install Python Playwright and Chromium first. It starts the real PHP endpoint against fresh private storage and a local sendmail sink, then verifies notification content and recovery without sending operational mail. See [operations](kitOrderOperations.md) for configuration and limitations. Production acceptance remains separate.
