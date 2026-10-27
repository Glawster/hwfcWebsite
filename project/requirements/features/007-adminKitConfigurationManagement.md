# 007: Admin kit configuration management

## Status

ToDo

## Outcome

As a `kit administrator`, I need to manage routine Club Shop configuration from the protected administration area so that kit managers, catalogue items and other operational shop settings can be maintained without editing deployment files by hand.

## Context

REQ-001 introduced a server-side `kitConfig.json` containing a mixture of deployment settings and routine Club Shop settings. REQ-006 separates the optional kit-order window into its own application-managed configuration file.

The same separation should be applied to other routine Club Shop settings. Values that club administrators reasonably need to change should be stored in a separate application-managed configuration file writable by the authenticated administration application, while deployment-sensitive settings remain outside the administrator-managed configuration.

The initial application-managed file should be `config/kitShop.json` beneath the existing protected `/config` directory. The file must not be committed to Git and must not be publicly readable over HTTP.

## Scope

- Add Club Shop configuration management within the protected `/admin/` area.
- Store administrator-managed Club Shop settings in a separate protected server-side JSON file, initially `config/kitShop.json`.
- Do not allow the administration application to rewrite the deployment-managed `config/kitConfig.json` file.
- Retain deployment-sensitive and operational settings in `kitConfig.json`, including at least:
  - `storageDir`;
  - `fromEmail`;
  - filesystem paths;
  - service credentials or secrets; and
  - other settings that should not be writable by routine Club Shop administration.
- Move routine Club Shop configuration to `kitShop.json`, including at least:
  - kit-manager email addresses;
  - delivery charge and delivery-related shop settings intended for routine administration; and
  - the product catalogue.
- Support more than one kit-manager email address using a `managerEmails` collection rather than a single `managerEmail` value.
- Allow an authorised administrator to add, edit and remove kit-manager email addresses.
- Require at least one valid kit-manager email address before the configuration can be saved.
- Allow an authorised administrator to add a new catalogue product.
- Allow an authorised administrator to edit an existing catalogue product.
- Allow a catalogue product to be disabled or removed from new ordering without invalidating existing stored orders that reference it.
- For each product, support the configuration fields required by REQ-001, including:
  - product identifier;
  - product name;
  - description;
  - price;
  - available sizes;
  - whether supplier-applied initials are required;
  - initials charge;
  - whether the product is eligible for the new-member free-shirt entitlement;
  - maximum quantity; and
  - optional local image path.
- Preserve the existing product identifier rules and prevent duplicate product identifiers.
- Validate all administrator changes using the same authoritative catalogue and pricing validation rules used by the public Club Shop.
- Combine the deployment-managed configuration and application-managed configuration into one effective in-memory shop configuration for the existing Club Shop code.
- Keep REQ-006 kit-window configuration separate in `config/kitWindow.json`.
- Protect all configuration-management actions using the administrator authentication and CSRF controls defined by REQ-003.
- Write `kitShop.json` atomically so an interrupted or failed update cannot leave the live shop configuration partially written or invalid.
- Validate the complete proposed `kitShop.json` content before replacing the currently valid file.
- Preserve the previously valid configuration if validation or persistence fails.
- Record sufficient audit information to identify which authenticated administrator changed the Club Shop configuration and when, without storing sensitive authentication material in the audit record.

## Out of scope

- Editing deployment credentials, filesystem paths, mail-provider credentials, SMS-provider credentials or other secrets through `/admin/`.
- Editing `kitConfig.json` directly from the administration application.
- Kit-order window management; this belongs to REQ-006 and uses `kitWindow.json`.
- Uploading or editing image binary files in the initial implementation; catalogue entries may reference approved local asset paths.
- Automatic supplier catalogue synchronisation.
- Automatic supplier stock or price updates.
- Rewriting historical accepted orders when a product is later changed, disabled or removed.

## Acceptance criteria

1. Given an authenticated authorised administrator, when they open the Club Shop administration area, then they can view the current administrator-managed shop configuration.
2. Given the deployed application, when the repository and runtime files are inspected, then routine administrator-managed Club Shop configuration is held in `config/kitShop.json` and deployment-sensitive settings remain in `config/kitConfig.json`.
3. Given an administrator adds a valid kit-manager email address, when the configuration is saved, then subsequent kit-order notifications are sent to all configured kit-manager email addresses according to the notification rules.
4. Given an administrator attempts to save an invalid email address or remove the final kit-manager email address, when validation runs, then the update is rejected and the previous configuration remains active.
5. Given an administrator adds a valid catalogue product, when the configuration is saved, then the new product becomes available to the public Club Shop without a code deployment.
6. Given an administrator edits a product price, sizes, initials settings, free-shirt eligibility, quantity limit or display information, when the configuration is saved, then subsequent new orders use the updated configuration.
7. Given an administrator disables or removes a product, when the configuration is saved, then the product is no longer available for new orders while previously accepted orders remain readable and unchanged.
8. Given an administrator attempts to create a duplicate or invalid product identifier, when the configuration is saved, then the change is rejected without altering the currently valid configuration.
9. Given an invalid catalogue field, price, quantity, size collection, image path or initials rule, when the administrator attempts to save it, then the same authoritative validation constraints used by the Club Shop reject the update.
10. Given a persistence failure during a configuration update, when the write fails, then the previously valid `kitShop.json` remains intact and usable.
11. Given an unauthenticated or insufficiently authorised request, when a Club Shop configuration change is attempted, then no configuration change is made.
12. Given a state-changing configuration request without a valid CSRF token, when it is submitted, then no configuration change is made.
13. Given a successful configuration change, when the audit information is reviewed, then the administrator identity, timestamp and nature of the change can be determined without exposing passwords, PINs or service secrets.
14. Given the public Club Shop loads its configuration, when both deployment and administrator-managed files are valid, then the application combines them into one effective configuration without requiring the existing ordering workflow to know which file supplied each value.
15. Given REQ-006 is also implemented, when a kit window is configured, then `kitWindow.json` remains independent of `kitShop.json` and both are combined with deployment configuration only at runtime.

## Dependencies and decisions

- Depends on REQ-001 Member kit ordering for product, pricing, delivery and notification behaviour.
- Depends on REQ-003 Admin authentication for protected access to `/admin/`.
- Complements REQ-006 Admin kit window management; the two requirements use separate application-managed configuration files.
- `config/kitConfig.json` remains deployment-managed and must not be writable by routine administration features.
- `config/kitShop.json` is application-managed and may be writable by the web application account, but it remains protected from public HTTP access by the existing `/config` protections.
- The effective configuration model is: deployment configuration + Club Shop configuration + optional kit-window configuration.
- Kit-manager notification recipients are represented by `managerEmails` rather than a single `managerEmail` value.
- Existing stored order records remain self-contained historical records and must not depend on the current catalogue still containing the same product.
- Configuration writes must use an atomic replace strategy such as writing and validating a temporary file before rename.

## Verification

- Unit tests for merging deployment and administrator-managed configuration into one effective configuration.
- Unit tests for `managerEmails` validation, including multiple recipients, invalid addresses and prevention of an empty recipient list.
- Unit tests for valid and invalid product creation and editing.
- Unit tests proving duplicate product identifiers are rejected.
- Unit tests proving disabled/removed products no longer appear for new ordering while existing order records remain readable.
- Integration tests proving administrator changes to products and notification recipients are used by subsequent public orders.
- Integration test proving `kitConfig.json` is not rewritten when Club Shop settings are changed.
- Security tests proving unauthenticated and invalid-CSRF requests cannot alter `kitShop.json`.
- Failure-path test proving an interrupted or failed write preserves the previous valid `kitShop.json`.
- HTTP protection test proving `config/kitShop.json` cannot be retrieved publicly.
- Audit test proving successful changes record administrator identity and time without authentication secrets.

## Change history

- 2026-10-11: created — separate routine Club Shop configuration from deployment configuration and allow authenticated administrators to manage kit-manager email recipients and the product catalogue through `/admin/`.
