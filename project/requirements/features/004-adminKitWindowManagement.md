# 004: Admin kit window management

## Status

ToDo

## Outcome

As a `kit administrator`, I need to add and manage a kit-order window from the private administration area so that time-limited ordering can be enabled when required without editing the deployment JSON configuration by hand.

## Context

REQ-001 supports two operating modes for the Club Shop:

- continuous ordering when no kit window is configured; and
- time-limited ordering when a kit window with opening and closing dates is configured.

The window feature is deliberately optional. The club may normally leave ordering open continuously but may want to introduce a defined ordering period for a supplier batch in future.

The existing kit-window logic remains the authoritative enforcement mechanism. This requirement adds an administrator-facing way to configure that mechanism.

## Scope

- Add a Kit Window administration option within the protected `/admin/` area.
- Allow an authorised kit administrator to create a kit window without editing `kitConfig.json` manually.
- Capture at least:
  - window identifier;
  - display name;
  - opening date and time; and
  - closing date and time.
- Validate that the opening date/time is earlier than the closing date/time.
- Store dates with an explicit timezone or convert administrator-entered local dates to an unambiguous timezone-aware representation before storage.
- Reuse the existing server-side kit-window validation and ordering-state behaviour rather than creating a second independent implementation.
- When a valid window is active, the Club Shop must continue to use the existing before/open/closed behaviour.
- When no window is configured, the Club Shop must continue to operate in continuous-ordering mode.
- Provide an administrator-visible summary of the currently configured window and its current state.
- Allow the configured window to be cleared/disabled so the shop can return to continuous ordering.
- Protect all window-management actions using the administrator authentication and CSRF protections defined by REQ-003.
- Ensure a failed or invalid update does not leave the live kit configuration partially written or unusable.

## Out of scope

- Multiple simultaneous active kit windows.
- Historical reporting across previous windows or supplier batches beyond retaining data already recorded on accepted orders.
- Automatic creation of supplier orders when a window closes.
- Member-facing administration of kit windows.
- Changes to the underlying before/open/closed ordering rules defined by REQ-001.

## Acceptance criteria

1. Given an authenticated authorised administrator, when they open the kit administration area, then an Add Kit Window option is available.
2. Given no kit window is configured, when the administrator views the kit administration area, then continuous ordering is shown as the current operating mode.
3. Given valid window details, when the administrator saves the new window, then the window is persisted and becomes the authoritative window used by the Club Shop.
4. Given an opening date/time that is not earlier than the closing date/time, when the administrator attempts to save the window, then the update is rejected and the existing live configuration remains unchanged.
5. Given a configured future window, when the public Club Shop is accessed before its opening time, then ordering remains unavailable according to the existing REQ-001 window behaviour.
6. Given a configured active window, when the public Club Shop is accessed between its opening and closing times, then ordering is available and the configured closing date can be presented by the existing shop UI.
7. Given a configured window whose closing time has passed, when the public Club Shop is accessed, then ordering is unavailable according to the existing REQ-001 window behaviour.
8. Given a configured window, when an authorised administrator clears/disables it, then the Club Shop returns to continuous-ordering mode without requiring a manual configuration-file edit.
9. Given an unauthenticated or insufficiently authorised request, when a kit-window management action is attempted, then no configuration change is made.
10. Given a state-changing window-management request without a valid CSRF token, when it is submitted, then no configuration change is made.
11. Given a storage or validation failure while saving a window, when the operation fails, then the previously valid kit configuration remains usable.

## Dependencies and decisions

- Depends on REQ-001 Member kit ordering for the existing optional kit-window model and public ordering behaviour.
- Depends on REQ-003 Admin authentication for protected access to `/admin/`.
- The initial implementation supports at most one configured kit window at a time because REQ-001 currently models a single optional `window` object.
- No window configured means continuous ordering; administrators must not have to create artificial dates simply to keep the shop open.
- The administrator UI is a management layer over the existing kit configuration. The implementation must preserve the same validation rules used when configuration is loaded by the Club Shop.
- The persistence mechanism must be designed so updates are atomic and do not expose the protected configuration through public HTTP access.

## Verification

- Unit tests for valid window creation, invalid date ordering, timezone-aware persistence and clearing a window.
- Integration tests proving an administrator-created window is subsequently honoured by `kitWindowState()` and order validation.
- Integration test proving no configured window leaves the shop continuously open.
- Security tests proving unauthenticated and invalid-CSRF requests cannot alter kit-window configuration.
- Failure-path test proving a failed write preserves the previous valid configuration.
- Browser test for the Add Kit Window administration form and current-window summary.

## Change history

- 2026-10-11: created — add administrator management of the optional REQ-001 kit-order window so future time-limited ordering can be enabled without manual JSON editing.
