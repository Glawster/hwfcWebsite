# Requirements

Next available number: 009

| Req ID | Requirement | Description | Status | Agent Prompt | Architecture Decisions |
| --- | --- | --- | --- | --- | --- |
| 001 | [Member kit ordering](features/001-memberKitOrdering.md) | Allow HWFC members to order club kit and pay by bank transfer. | Completed | [Prompt](prompt/001-memberKitOrdering.md) | [001](../adr/001-privateKitOrderRecords.md) |
| 002 | [Kit order tracking](features/002-kitOrderTracking.md) | Track accepted kit orders through payment, supplier fulfilment, receipt and member handover. | ToDo | — | — |
| 003 | [Admin authentication](features/003-adminAuthentication.md) | Protect the private `/admin/` area with email, password and a six-digit SMS PIN on every login. | ToDo | — | — |
| 004 | [Membership application and management](features/004-membershipApplicationAndManagement.md) | Accept membership applications, maintain the member register, support GDPR administration, issue Membership IDs and provide virtual membership cards. | InProgress | — | — |
| 005 | [Club privacy notice](features/005-clubPrivacyNotice.md) | Publish and maintain accessible club privacy information. | Completed | — | — |
| 006 | [Administration page](features/006-adminPage.md) | Provide the private administration dashboard that brings membership, kit and future club administration functions together. | InProgress | — | — |
| 007 | [Admin kit configuration management](features/007-adminKitConfigurationManagement.md) | Allow authenticated administrators to manage routine Club Shop settings, kit-manager email recipients and catalogue products from `/admin/`. | ToDo | — | — |

| 008 | [Admin kit window management](features/008-adminKitWindowManagement.md) | Allow an authenticated administrator to add, review and clear the optional kit-order window from `/admin/`. | ToDo | — | — |

## Prompt index

<!-- OMP-PROMPT-INDEX-BEGIN -->
- [001-memberKitOrdering](prompt/001-memberKitOrdering.md)
<!-- OMP-PROMPT-INDEX-END -->
