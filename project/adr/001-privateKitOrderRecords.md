# 001: Private kit-order records before notification

Date: 2026-10-08
Status: Accepted
Requirement: [001: Member kit ordering](../requirements/features/001-memberKitOrdering.md)

## Context

REQ-001 requires recoverable kit-manager mail failures and batch tracking while preserving lightweight PHP hosting. A database, login and full administration dashboard are outside its scope.

## Decision

Store each accepted order as a private JSON record outside the public document root before attempting PHP `mail()`. Record the batch, validated summary, payment status and notification state. Use exclusive creation, restricted permissions, atomic notification-status replacement and a per-order retry lock. Provide a CLI-only retry command that operators can schedule and monitor.

The web flow uses a server-side session review and explicit final submission, rechecking both the window and configuration. Sessions retain only recognised validated fields, and repeat final submissions return the previous confirmation.

## Consequences

The host must configure, monitor, protect and back up private storage and the retry command. The site has no status portal or administration UI. Payment matching, entitlement checking and supplier lifecycle remain manual. External mail acceptance and file updates cannot form one transaction; a crash between them may cause duplicate notifications. Kit managers deduplicate by the immutable reference. Production inbox delivery still requires separate cPanel evidence.
