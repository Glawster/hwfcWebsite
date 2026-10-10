# 001: Member kit ordering — implementation prompt

Implement requirement `project/requirements/features/001-memberKitOrdering.md`.

## Role

Implement and verify the member kit-ordering workflow for the HWFC website.

## Required outcome

Deliver the `/kit/` ordering flow defined by requirement 001 without broadening the scope.

## Constraints

- Preserve the existing lightweight HTML/CSS/PHP architecture.
- Reuse existing shared styling and the Contact form's PHP validation/mail approach where suitable.
- Keep product names, sizes, prices, initials support, free-shirt eligibility and charges in server-controlled configuration.
- Support configured kit-order opening and closing date/times; the server must reject orders outside the active window.
- Show the closing date clearly while ordering is open.
- Associate accepted orders with the active kit batch/window.
- Include a new-member checkpoint for claiming the one free club shirt.
- Apply the free-shirt entitlement to at most one configured eligible shirt unit.
- Do not infer entitlement automatically; record the member's claim and expose it clearly to the kit manager for administrative checking.
- Immediately email the complete accepted order to the kit manager.
- Load the kit manager email address from server-side configuration; do not hard-code the live address in source control.
- Define a recoverable path for kit-manager mail failure; do not silently lose the order.
- Do not trust browser-posted prices, totals, entitlement state or window state.
- Payment is bank transfer only.
- Supplier-applied initials are supported only for configured products.
- Do not add shirt-number capture.
- Do not request or store member banking credentials or card information.
- Do not commit live club bank details or other secrets.
- Keep the form practical on mobile devices.
- Follow `documentation/kitOrderPageCopyAndWireframe.md` for page copy and layout intent.

## Work boundaries

Expected areas include:

- `/kit/` page and form handling;
- shared CSS only where required;
- server-side kit/product, entitlement and order-window configuration;
- server-side validation and total calculation;
- order-reference and batch association;
- complete kit-manager email notification;
- member confirmation;
- relevant tests or repeatable verification evidence;
- durable documentation updates required by delivered behaviour.

Do not introduce a member login system, membership database integration, payment gateway, supplier API or full admin dashboard under this requirement.

## Acceptance criteria

Implement and verify every acceptance criterion in `project/requirements/features/001-memberKitOrdering.md`.

## Verification

At minimum verify:

- page behaviour before, during and after a configured kit window;
- server rejection of submissions outside the window;
- one-item and multi-item orders;
- size and quantity validation;
- initials-capable and non-initials products;
- absence of shirt-number handling;
- new-member free-shirt claim with an eligible shirt;
- no more than one shirt unit receives the free entitlement;
- a free-shirt claim without an eligible shirt is rejected;
- additional shirt units remain chargeable;
- free-shirt status appears in review, confirmation and kit-manager email;
- collection and delivery paths;
- manipulated browser prices/totals/entitlement flags do not control the server total;
- accepted orders carry the correct batch/window identifier;
- bank-transfer reference and confirmation content;
- complete order email reaches a configured test kit-manager address;
- kit-manager mail failure handling;
- validation failures preserve safe user input where practical;
- honeypot and human-confirmation behaviour;
- mobile layout;
- outbound email on the production cPanel host before completion;
- no live bank details, kit-manager address or other secrets are committed.

## Handoff

Report:

- files changed;
- verification performed and results;
- acceptance criteria satisfied;
- any criterion not yet satisfied;
- any discovered requirement change that should be handled through change control rather than silently implemented.
