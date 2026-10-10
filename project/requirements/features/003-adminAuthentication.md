# 003: Admin authentication

## Status

ToDo

## Outcome

As a `club administrator`, I need secure access to the private administration area using my email address, password and a one-time SMS PIN so that sensitive club administration functions are protected from unauthorised access.

## Context

HWFC requires a private administration area at `/admin/` for managing kit orders and future club administration functions. The administration area must not appear in the public website navigation.

Each administrator will have an account identified by their email address. Access must require both a password and a six-digit confirmation PIN sent by SMS to the mobile number registered for that administrator on every new login.

The authentication mechanism should be reusable by future administration features rather than being coupled only to kit ordering.

## Scope

- Provide a private administration entry point at `/admin/`.
- Do not expose `/admin/` through the public site navigation.
- Identify administrator accounts by email address.
- Store administrator passwords only as secure password hashes.
- Associate each administrator account with a mobile telephone number used only as the SMS second-factor destination.
- Require email address and password as the first authentication step.
- After successful password authentication, generate a cryptographically secure six-digit numeric PIN.
- Send the PIN by SMS to the administrator's registered mobile number.
- Require successful PIN verification before an authenticated administrator session is established.
- Require a new SMS PIN for every new login.
- Make PINs single-use.
- Expire PINs after a short period; the initial implementation should use 10 minutes unless changed by configuration or a later decision.
- Limit incorrect PIN attempts; the initial implementation should allow no more than 5 attempts per issued PIN.
- Rate-limit repeated authentication failures and PIN requests.
- Do not reveal whether an administrator email address exists when authentication fails.
- Support explicit logout and invalidate the authenticated session on logout.
- Expire authenticated administrator sessions after inactivity.
- Protect authenticated session cookies with `Secure`, `HttpOnly` and an appropriate `SameSite` policy.
- Protect state-changing administration requests against CSRF.
- Prevent disabled administrator accounts from authenticating.
- Store SMS provider credentials and other authentication secrets outside Git and public web content.
- Keep the SMS sending implementation behind a provider-neutral application boundary so the SMS provider can be changed without changing the administration workflows.
- Record sufficient authentication audit information to support troubleshooting and security review without recording passwords or plaintext PINs.

## Out of scope

- Kit-order workflow and order status management; these belong to REQ-002.
- Public self-registration for administrator accounts.
- Passwordless login.
- Remember-this-device or bypassing the SMS PIN on trusted devices.
- Using the administrator mobile number as the account identity.
- Member accounts or general website user accounts.
- Selection of a specific SMS provider; provider choice may be implemented later without changing this requirement.
- Recovery codes, authenticator applications or hardware security keys in the initial implementation.

## Acceptance criteria

1. Given an unauthenticated visitor, when they visit `/admin/`, then they are presented with the administrator login flow and cannot access protected administration content.
2. Given the public website, when navigation is displayed, then no `/admin/` link is present in the public menu.
3. Given a valid enabled administrator account, when the correct email address and password are submitted, then a cryptographically secure six-digit numeric PIN is generated and sent to the mobile number registered for that administrator.
4. Given a valid password authentication but no successful PIN verification, when the administrator attempts to access protected administration content, then access is denied.
5. Given a valid unexpired PIN, when the administrator submits it correctly, then the PIN is invalidated and an authenticated administrator session is established.
6. Given a PIN that has already been successfully used, when it is submitted again, then it is rejected.
7. Given a PIN older than the configured validity period, when it is submitted, then it is rejected.
8. Given repeated incorrect PIN submissions, when the configured attempt limit is reached, then that PIN can no longer be used and a new authentication attempt is required.
9. Given repeated failed password attempts or repeated PIN requests, when rate limits are exceeded, then further attempts are temporarily restricted.
10. Given an unknown email address, disabled account or incorrect password, when login fails, then the response does not disclose which condition caused the failure.
11. Given an authenticated administrator, when they explicitly log out, then their administrator session is invalidated and protected administration pages require authentication again.
12. Given an authenticated administrator session that has exceeded the configured inactivity period, when another protected request is made, then re-authentication is required.
13. Given administrator credentials stored by the application, when storage is inspected, then passwords and active PINs are not stored in plaintext.
14. Given an authenticated state-changing administration request, when the CSRF token is missing or invalid, then the request is rejected without changing application state.
15. Given deployment configuration, when the repository is inspected, then SMS provider credentials and other authentication secrets are not committed to Git.
16. Given a future replacement SMS provider, when the provider implementation is changed, then the administration login workflow does not require redesign because SMS delivery is accessed through a provider-neutral boundary.

## Dependencies and decisions

- REQ-002 Kit Order Tracking will depend on this requirement for access control to its administration pages.
- Administrator email address is the stable account identity.
- Mobile telephone number is authentication metadata used only as the second-factor destination and is not the login identity.
- Password hashing should use PHP's `password_hash()` and `password_verify()` APIs with the strongest supported password hashing algorithm suitable for the deployed PHP environment.
- PIN generation must use a cryptographically secure random source rather than `rand()` or similar non-cryptographic generators.
- Plaintext PINs must not be retained after verification data has been created.
- The SMS provider and its API are deliberately not fixed by this requirement.

## Verification

- Unit tests for password verification, PIN generation, PIN hashing, expiry, single-use behaviour and attempt limits.
- Integration tests for successful two-step login, invalid password, invalid PIN, expired PIN, disabled account and logout.
- Security tests proving protected `/admin/` pages cannot be accessed before both authentication steps complete.
- Tests proving login failures do not disclose whether the supplied email address exists.
- Tests for authentication and PIN-request rate limiting.
- CSRF tests for authenticated state-changing requests.
- Session expiry and logout tests.
- Deployment review confirming authentication and SMS secrets are not present in Git or public web content.
- Production acceptance test confirming SMS delivery to an authorised test administrator before the administration area is released for use.

## Change history

- 2026-10-10: created — define reusable two-factor administrator authentication for the private `/admin/` area using email, password and a six-digit SMS PIN on every login.
