# Kit ordering operations

Implements [REQ-001](../project/requirements/features/001-memberKitOrdering.md) using PHP 8.1+, shared CSS and progressive JavaScript. `/kit/` remains available outside ordering windows. No order data is sent in query strings.

## Private deployment configuration

1. Copy `kit/config.example.json` to a **private directory outside the entire public document root**, for example a directory under the cPanel account home. The example catalogue, charges and bank values are illustrative and require club approval/replacement before use.
2. Create a private writable order directory outside the public root. Restrict its permissions to `0700` and configuration to `0600`, accessible to the PHP/cron account only. Use an absolute `storage_dir` path.
3. Set `HWFC_KIT_CONFIG` to the absolute JSON configuration path in the hosting environment. Depending on cPanel configuration this may be an Apache `SetEnv` directive outside source control or a host-managed environment setting. Confirm the variable reaches web PHP and explicitly set it for cron. Do not place private JSON inside the checkout.
4. Configure `manager_email`, domain-authorised `from_email`, club bank destination, approved products and prices, delivery charge and window. Dates must be ISO 8601 timestamps with explicit UTC offsets. Display dates use Europe/London; the opening instant is inclusive and the closing instant exclusive. Each batch needs a distinct window `id`.
5. Confirm PHP sessions and `mail()` work on the host. Use HTTPS. Configuration errors hide the form and return HTTP 503; they never activate the example catalogue automatically.

Product amounts are whole pence. Each product controls allowed sizes, aggregate maximum quantity across its order lines, initials support/charge and free-shirt eligibility. Initials are optional, one to four ASCII letters, preserved exactly as entered. A claim discounts the base cost of one eligible unit, choosing the first eligible line in catalogue/form order. Initials charges apply to every personalised unit and delivery is added separately. The kit manager must check eligibility and previous entitlement use manually.

`delivery_required` must contain `address1`, `city`, `postcode` and can also require recipient, county or address line 2. Collection does not retain an address. The form offers three size/initials lines per product without JavaScript and additional lines with JavaScript, up to 60 lines per order. All product quantity limits still apply across lines.

## Acceptance and notifications

The first POST validates and calculates a review. The final POST revalidates the server-held input against the current window and configuration. If settings change, the member must review again. A session token prevents a repeated final POST from creating a second order. Opening a fresh order/review remains possible; there is no identity-level duplicate-order or entitlement database.

Accepted records have a random order reference, batch ID/name, timestamp, full validated summary and initial `Payment Pending` status. A reference uses `KIT-YYYYMMDD-XXXXXXXX`; confirm the club bank permits this 21-character transfer reference before launch. Private order creation is exclusive, so a collision cannot overwrite another order (the member receives a save failure and can retry). No notification is sent until the record has been written and flushed.

The kit-manager summary is submitted immediately to PHP `mail()`. A successful return means the mail service accepted it, **not proof of inbox delivery**. A member confirmation email is attempted after manager-mail acceptance. The complete confirmation and bank instructions are always available on screen in the submitting session, even if mail fails. Save/print the page if needed; refreshing it does not accept another order. It is not a member status portal.

## Recovery

Each private JSON record tracks `manager_mail` (`pending`, `failed`, `sent`), attempt count and `member_mail`. A false return or exception from mail preserves the order and displays an explicit warning with its reference. Failures also write a reference-only PHP error-log entry. Orders are not silently dropped and members are told not to resubmit.

Run from the hosting account:

```sh
HWFC_KIT_CONFIG=/absolute/private/path/kit.json php kit/retryNotifications.php
```

The command scans private records, retries manager notifications that are pending/failed and member confirmations that remain unsent, and reports reference/status only. Already-sent notifications are skipped. A per-order lock prevents competing retries. The command returns nonzero when delivery or storage still fails and cannot run through HTTP. Configure a monitored cron invocation (for example every five minutes) and inspect both failures and PHP logs. Confirm the cron user can access the same private configuration and files.

If the process dies after mail-service acceptance but before saving notification state, retry may send a duplicate email. The order reference is unchanged: kit managers must deduplicate supplier orders by reference. Before retrying a suspected crash, check the inbox/log and reconcile the saved status. Do not promise exactly-once external email delivery.

Protect and back up the private directory with access restricted to authorised club administrators. Agree an operational retention/deletion period with the club; the application does not introduce automatic deletion. Personal data and bank configuration must not enter Git, public backup files or public URLs. Monitor pending/failed records, including records created immediately before a process failure.

The business lifecycle supports Submitted → Payment Pending → Paid → Ordered → Ready for Collection / Dispatched → Complete, or Cancelled. Submitted is represented by the acceptance timestamp; records initially carry Payment Pending. Later status/payment matching and supplier tracking are handled manually by the kit manager outside the member-facing site. There is no admin dashboard or automated payment verification.

## Local verification

Install PHP 8.1+ with PHPUnit 10's required extensions and PHPUnit 10 (a PHAR is sufficient). Then run:

```sh
php /path/to/phpunit-10.phar -c phpunit.xml
python3 -m pip install playwright
python3 -m playwright install chromium
HWFC_TEST_PHP=php python3 tests/test_kitHttp.py
```

PHPUnit checks rules and the real validation → calculation → persistence → summary → notification composition, replacing only mail delivery. The Python suite creates a fresh private config/storage/session directory and actual PHP HTTP server. PHP `mail()` invokes `tests/mailSink.py`, which captures messages without sending network mail. It checks POST tampering, windows, closure after review, confirmation, idempotent repeat POST, mail failures and real CLI retry. Playwright exercises four viewports and the no-JavaScript flow. Test addresses use `example.invalid`; no live configuration is needed. Python modules follow `test_camelCaseName.py`; when invoking pytest use `python_files = test_[a-z]*.py`.

## Required production evidence

Before marking REQ-001 complete, follow [kitOrderTesting.md](kitOrderTesting.md#7-production-acceptance-checks) on cPanel with a test destination and temporary window. Verify actual inbox receipt, complete manager/member content, free-shirt totals, bank/reference instructions, window rejection and private configuration behavior. Record evidence in `project/currentIncrement.md`. Local mail-sink success cannot establish production inbox delivery. Deploy only after approving catalogue/payment details and configuring monitored recovery.
