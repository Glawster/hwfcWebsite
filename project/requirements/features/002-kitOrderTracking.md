# 002: Kit order tracking

## Status

ToDo

## Outcome

As a `kit administrator`, I need to track each accepted kit order through payment, supplier fulfilment, receipt and handover so that no member order is lost or left in an unclear state.

## Context

REQ-001 captures member kit ordering. Once an order has been accepted, the club also needs an internal workflow for following that order through to completion.

Further detail will be added before implementation.

## Scope

- Track whether payment has been received.
- Track whether the order has been placed with the supplier.
- Track whether the goods have been received from the supplier.
- Track whether the goods have been delivered to, collected by, or otherwise passed on to the member.
- Provide an administrator-facing way to view and update order progress; an admin portal is the current likely approach.
- Retain the existing order reference as the link between the member order and its internal tracking record.

## Out of scope

- Final workflow states and transition rules, pending further requirements.
- Authentication and administrator roles, pending further requirements.
- Supplier integration or automated payment reconciliation, pending further requirements.

## Acceptance criteria

1. Given an accepted kit order, when an administrator views it, then its payment, supplier-order, goods-received and member-handover state can be determined.
2. Given an accepted kit order, when its fulfilment progresses, then an authorised administrator can record the corresponding status changes.
3. Given a tracked order, when it is viewed later, then the current state remains associated with the original kit order reference.

## Dependencies and decisions

- Depends on REQ-001 Member kit ordering.
- Detailed workflow and administration design to follow before implementation.

## Verification

- To be defined when the workflow and administrator interface are specified.

## Change history

- 2026-10-08: created from initial requirement to track supplier placement, money received, goods received and member handover; detailed design to follow.
