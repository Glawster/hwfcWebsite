# Current increment

## 001: Member kit ordering

[Requirement](requirements/features/001-memberKitOrdering.md) · [Prompt](requirements/prompt/001-memberKitOrdering.md)

Branch: `feature/001-member-kit-ordering`

Implementation includes `/kit/`, server-controlled configuration, window checks, order review, per-line sizing/initials/quantity, one free-shirt claim, whole-pence totals, private durable order records, immediate manager notification, complete confirmation and CLI notification retry. See [operations](../documentation/kitOrderOperations.md).

Verification on 8 October 2026:

- PHP 8.1 syntax checks pass for all new PHP files.
- PHPUnit 10: 53 tests, 94 assertions passed.
- HTTP/mail-sink and Playwright: 7 integration/browser tests passed, including 375×667, 390×844, 768×1024 and 1280×800 plus a no-JavaScript mobile flow.

Acceptance criteria 1–18 and 20–28 have implementation and local rule/HTTP/browser evidence; criterion 19 has local mail-service composition evidence only. Production delivery evidence remains outstanding. Requirement stays `InProgress`.

Remaining acceptance work:

- Approve and configure actual catalogue, prices, initials rules, delivery charge, bank destination and window on cPanel.
- Configure private storage and monitored notification retry.
- Verify the 21-character generated payment reference is supported by the receiving bank.
- Run the required real production cPanel mail/inbox and configuration acceptance checks in `documentation/kitOrderTesting.md`; record results here. No host access or live operational configuration was available during local implementation.

No scope change was introduced. Membership/entitlement checking remains manual; no login, gateway or dashboard has been added.
