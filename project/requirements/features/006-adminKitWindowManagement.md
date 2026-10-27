# 006: Admin kit window management

## Status

ToDo

## Outcome

As a `kit administrator`, I need to add and manage a kit-order window from the private administration area so that time-limited ordering can be enabled when required without editing the main deployment configuration by hand.

## Context

REQ-001 supports two operating modes for the Club Shop:

- continuous ordering when no kit window is configured; and
- time-limited ordering when a kit window with opening and closing dates is configured.

The window feature is deliberately optional. The club may normally leave ordering open continuously but may want to introduce a defined ordering period for a supplier batch in future.

Kit-window state must be stored separately from the main `kitConfig.json`. The main kit configuration contains deployment-managed settings such as catalogue, pricing, bank details, mail settings and delivery rules, while the kit-window file is application-managed state that the authenticated administration area is permitted to update.

The existing kit-window logic remains the authoritative enforcement mechanism. This requirement adds an administrator-facing way to manage the separate window configuration used by that mechanism.

## Scope

- Add a Kit Window administration option within the protected `/admin/` area.
- Store kit-window configuration in a separate server-side JSON file from `kitConfig.json`.
- The administration application must have write access to the kit-window configuration file, while `kitConfig.json` remains deployment-managed and must not need to be rewritten by the admin UI.
- Allow an authorised kit administrator to create a kit window without editing configuration files manually.
- Capture at least:
  - window identifier;
  - display name;
  - opening date and time; and
  - closing date and time.
- Validate that the opening date/time is earlier than the closing date/time.
- Store dates with an explicit timezone or convert administrator-entered local dates to an unambiguous timezone-aware representation before storage.
- Reuse the existing server-side kit-window validation and ordering-state behaviour rather than creating a second independent implementation.
- When a valid window file is present, the Club Shop must use the existing before/open/closed behaviour.
- When no window configuration exists, or the separate window file represents no active window, the Club Shop must operate in continuous-ordering mode.
- Provide an administrator-visible summary of the currently configured window and its current state.
- Allow the configured window to be cleared/disabled so the shop can return to continuous ordering.
- Protect all window-management actions using the administrator authentication and CSRF protections defined by REQ-003.
- Ensure a failed or invalid update does not leave the live window configuration partially written or unusable.
- Keep the window configuration protected from direct public HTTP access in the same way as other private server configuration.
- Write updates atomically, using a temporary file and rename or equivalent safe replacement mechanism, so readers see either the previous valid file or the new valid file.

## Out of scope

- Allowing the admin UI to modify `kitConfig.json`.
- Multiple simultaneous active kit windows.
- Historical reporting across previous windows or supplier batches beyond retaining data already recorded on accepted orders.
- Automatic creation of supplier orders when a window closes.
- Member-facing administration of kit windows.
- Changes to the underlying before/open/closed ordering rules defined by REQ-001.

## Acceptance criteria

1. Given an authenticated authorised administrator, when they open the kit administration area, then an Add Kit Window option is available.
2. Given no kit-window configuration exists, when the administrator views the kit administration area, then continuous ordering is shown as the current operating mode.
3. Given valid window details, when the administrator saves the new window, then the separate kit-window configuration file is persisted and becomes the authoritative window used by the Club Shop.
4. Given an opening date/time that is not earlier than the closing date/time, when the administrator attempts to save the window, then the update is rejected and the existing live window configuration remains unchanged.
5. Given a configured future window, when the public Club Shop is accessed before its opening time, then ordering remains unavailable according to the existing REQ-001 window behaviour.
6. Given a configured active window, when the public Club Shop is accessed between its opening and closing times, then ordering is available and the configured closing date can be presented by the existing shop UI.
7. Given a configured window whose closing time has passed, when the public Club Shop is accessed, then ordering is unavailable according to the existing REQ-001 window behaviour.
8. Given a configured window, when an authorised administrator clears/disables it, then the separate window configuration is removed or reset and the Club Shop returns to continuous-ordering mode without requiring a manual file edit.
9. Given an unauthenticated or insufficiently authorised request, when a kit-window management action is attempted, then no window configuration change is made.
10. Given a state-changing window-management request without a valid CSRF token, when it is submitted, then no window configuration change is made.
11. Given a storage or validation failure while saving a window, when the operation fails, then the previously valid window configuration remains usable.
12. Given an administrator changes a kit window, when repository or deployment-managed configuration is inspected, then `kitConfig.json` has not been rewritten by the administration feature.
13. Given the public web server, when a client attempts to request the kit-window configuration file directly, then the file contents are not exposed.

## Dependencies and decisions

- Depends on REQ-001 Member kit ordering for the existing optional kit-window model and public ordering behaviour.
- Depends on REQ-003 Admin authentication for protected access to `/admin/`.
- Kit-window configuration is deliberately separated from `kitConfig.json` because the former is mutable application state and the latter is deployment-managed configuration.
- The initial implementation supports at most one configured kit window at a time.
- No window configured means continuous ordering; administrators must not have to create artificial dates simply to keep the shop open.
- The Club Shop should load the main kit configuration and the separate optional kit-window configuration, then expose the combined configuration to the existing ordering code so window-state rules remain centralised.
- A suitable initial protected path is `config/kitWindow.json`, alongside but separate from `config/kitConfig.json`, provided the existing `/config` HTTP protection remains in place.
- `config/kitWindow.json` is server-managed runtime state and must not be committed with live values. A repository example file may be provided if useful.
- The persistence mechanism must validate the complete candidate window before replacing the current file and must use atomic replacement.

## Verification

- Unit tests for valid window creation, invalid date ordering, timezone-aware persistence and clearing a window.
- Unit tests proving `kitConfig.json` is not modified by window-management operations.
- Integration tests proving an administrator-created window in the separate file is subsequently honoured by `kitWindowState()` and order validation.
- Integration test proving an absent or empty window configuration leaves the shop continuously open.
- Security tests proving unauthenticated and invalid-CSRF requests cannot alter kit-window configuration.
- HTTP test proving the window configuration file cannot be downloaded publicly.
- Failure-path test proving a failed write preserves the previous valid window configuration.
- Browser test for the Add Kit Window administration form and current-window summary.

## Change history

- 2026-10-11: created — add administrator management of the optional REQ-001 kit-order window so future time-limited ordering can be enabled without manual JSON editing.
- 2026-10-11: revised — store mutable kit-window state in a separate protected server-side configuration file so the admin UI never rewrites deployment-managed `kitConfig.json`.
