# HWFC Kit Order Page – Copy and Wireframe

Status: Draft for REQ-001 implementation  
Route: `/kit/`  
Requirement: `../project/requirements/features/001-memberKitOrdering.md`

## 1. Page Purpose

The Kit page allows existing HWFC members to order approved club kit and receive bank-transfer payment instructions.

The page should be simple enough to complete comfortably on a mobile phone and should not require a member account or login.

Orders are accepted only during the configured kit-order window. While the window is open, the closing date must be shown clearly near the top of the page.

## 2. Page Introduction

### Heading

**Order Club Kit**

### Introductory copy

> Order Hillsborough Walking Football Club kit here. Select the items and sizes you need, add your initials where available, enter your contact and collection/delivery details, then submit your order. Payment is by bank transfer. You will receive an order reference to use as your payment reference.

Supporting notes:

> Please check sizes and initials carefully before submitting your order. Initials are applied by the supplier and should be entered exactly as required.

> **Orders for this kit window close: [configured closing date/time].**

Before the opening date or after the closing date, the order form must not accept submissions and should instead explain when ordering opens or that the current window has closed.

## 3. Available Kit Section

The paper form used as the design reference is fundamentally an order grid: garment, price, size, quantity and initials where applicable. The website should preserve that clarity while making it easier to use on mobile.

Each available item should show:

- Product image where available.
- Product name.
- Short description.
- Price.
- Available sizes.
- Quantity.
- Initials field only where the supplier offers initials on that garment.
- Free-shirt eligibility where relevant.

Example structure:

```text
+--------------------------------------------------+
| [Image]  HWFC Playing Shirt                     |
|          Official club playing shirt            |
|          £XX.XX                                 |
|                                                  |
|          Size:        [ M v ]                   |
|          Quantity:    [ 1 ]                     |
|          Initials:    [ AW  ]                   |
+--------------------------------------------------+
```

No shirt-number field is required.

A garment that does not support supplier-applied initials must not display the initials control.

The exact live products, prices, sizes, free-shirt eligibility and allowed personalisation combinations are server-controlled catalogue data and should not be hard-coded into this design document.

## 4. New Member Free Shirt

Each member is entitled to one free club shirt when joining.

Include a clear checkpoint in the order flow:

```text
[ ] I am a new member and I am claiming my one free club shirt
```

Supporting copy:

> New members are entitled to one free club shirt. Tick this box if you are claiming that entitlement with this order. The kit manager will check the entitlement before the supplier order is placed.

Behaviour:

- The free entitlement applies to only one configured eligible shirt unit.
- If the member orders additional shirts, those remain chargeable.
- If the checkbox is selected but no eligible shirt is in the order, show a clear validation error.
- The order summary must identify the free shirt as **New member free shirt – £0.00**.
- The member confirmation and kit-manager email must show that the entitlement was claimed.
- Personalisation or delivery charges remain payable unless configured otherwise.

## 5. Order Summary

A running order summary should appear after item selection.

Suggested heading:

**Your Order**

Example:

```text
HWFC Playing Shirt
Size: L
Qty: 1
Initials: AW
New member free shirt: £0.00

Training Top
Size: L
Qty: 1
£XX.XX

Delivery/collection: [not yet selected]

Order total: £XX.XX
```

On desktop this may sit beside the form if the existing design supports it cleanly. On mobile it should remain in the normal vertical flow.

The browser-side total is for convenience only. The PHP handler must recalculate the authoritative total, including the free-shirt rule.

## 6. Member Details

### Heading

**Your Details**

Fields:

```text
Full Name *
[                                      ]

Email Address *
[                                      ]

Mobile number *
[                                      ]

Section
[ Men's / Ladies' / Other / Prefer not to say v ]

If the bank transfer will come from an account in a different name:
Payer/account name
[                                      ]
```

Help text for payer/account name:

> Leave this blank if the bank account name will be the same as the name above. This helps us match your transfer to your order.

The form must not ask for the member's account number, sort code, card information or online-banking details.

## 7. Collection or Delivery

### Heading

**How would you like to receive your order?**

Use radio buttons or similarly clear controls:

```text
(o) Collect from the club
( ) Delivery
```

For collection:

> We will contact you when your order is ready and confirm where it can be collected.

When Delivery is selected, reveal:

```text
Recipient name
[                                      ]

Address line 1 *
[                                      ]

Address line 2
[                                      ]

Town / City *
[                                      ]

County
[                                      ]

Postcode *
[                                      ]
```

If a delivery charge is configured, it should be shown before the user submits the order and included in the displayed total.

## 8. Additional Notes

### Heading

**Anything else we need to know?**

Field:

```text
Order notes
[                                      ]
[                                      ]
[                                      ]
```

Suggested help text:

> Use this for information relevant to your order only. Please do not include banking or card details.

## 9. Payment Explanation Before Submission

### Heading

**Payment**

> Payment is by bank transfer. After you submit the order we will give you a unique order reference, the amount to pay and the club bank details. Please use the order reference as the reference on your bank transfer so we can match the payment to your order.

> Your order will initially be marked as payment pending. The club will process it once the payment has been matched.

## 10. Review Before Submission

### Heading

**Check Your Order**

The final review must clearly show:

- Member name.
- Contact email/phone.
- Whether the new-member free-shirt entitlement is being claimed.
- Every product.
- Size.
- Quantity.
- Initials where applicable.
- Which shirt receives the free entitlement.
- Line totals.
- Collection/delivery choice.
- Delivery charge where relevant.
- Final total.

Suggested action buttons:

```text
[ Back / Change Order ]     [ Submit Kit Order ]
```

## 11. Privacy and Human Check

Before the submit button include:

```text
[ ] I am human
```

Privacy wording:

> We use the information you provide to process your kit order, contact you about it, arrange collection or delivery and match your bank-transfer payment. Please do not enter card details, bank account numbers, passwords or other banking credentials in this form.

Include the same hidden honeypot technique used by the current Contact form.

## 12. Successful Order Confirmation

### Heading

**Thank you – your kit order has been received**

Show prominently:

```text
Order reference: KIT-YYYYMMDD-NNNN
Amount to pay: £XX.XX
```

Then list the complete order summary, including initials and the new-member free-shirt entitlement where applicable.

### Pay by bank transfer

Display the configured club payment details:

```text
Account name:  [configured club account name]
Sort code:     [configured value]
Account no.:   [configured value]
Amount:        £XX.XX
Reference:     KIT-YYYYMMDD-NNNN
```

> **Please use `KIT-YYYYMMDD-NNNN` as the payment reference.** This allows us to match your payment to your kit order.

> Your order is currently **Payment Pending**. We will process the order once the payment has been matched. We will contact you if we need any further information and again when the order is ready for collection or has been dispatched.

## 13. Kit Manager Email

Each accepted order must immediately generate a complete order email to the kit manager address stored in server-side configuration.

The email should clearly show:

- kit-window/batch identifier;
- order reference and submission time;
- member contact details;
- whether the new-member free-shirt entitlement is claimed;
- which garment receives that entitlement;
- every garment, size, quantity and initials;
- line totals and final total;
- collection/delivery details;
- payer name where provided;
- notes;
- payment status `Payment Pending`.

Suggested subject:

```text
HWFC Kit Order KIT-YYYYMMDD-NNNN – Member Name
```

## 14. Error Behaviour

Validation errors should appear in plain language and retain valid data where practical.

Examples:

```text
Please select at least one kit item.
Please choose a size for the HWFC Playing Shirt.
Please check the initials entered for the HWFC Playing Shirt.
You have claimed your free new-member shirt; please add an eligible club shirt to your order.
Please enter your email address.
Please enter a delivery address.
Please confirm that you are human.
```

A server/mail failure must not falsely tell the user that all administrative processing has completed.

## 15. Mobile Wireframe

```text
+----------------------------------+
| HWFC HEADER                      |
+----------------------------------+
| Order Club Kit                   |
| Closing date                     |
+----------------------------------+
| AVAILABLE KIT                    |
| [product card]                   |
| size / qty / initials            |
| [product card]                   |
+----------------------------------+
| NEW MEMBER?                      |
| [ ] claim free club shirt        |
+----------------------------------+
| YOUR ORDER                       |
| selected lines                   |
| free-shirt line if applicable    |
| total                            |
+----------------------------------+
| YOUR DETAILS                     |
| name / email / phone             |
| section / payer name             |
+----------------------------------+
| COLLECTION OR DELIVERY           |
| [address fields when required]   |
+----------------------------------+
| PAYMENT                          |
| Bank transfer explanation        |
+----------------------------------+
| CHECK YOUR ORDER                 |
| Privacy + human confirmation     |
| [ Submit Kit Order ]             |
+----------------------------------+
```

## 16. Desktop Wireframe

```text
+--------------------------------------------------------------+
| HWFC HEADER / NAV                                            |
+--------------------------------------------------------------+
| Order Club Kit                                               |
| Closing date                                                 |
+--------------------------------------------------------------+
| Available kit                          | Your Order           |
| [product: size/qty/initials]           | selected lines       |
| [product: size/qty/initials]           | free-shirt status    |
| New member free-shirt checkpoint       | running total        |
+--------------------------------------------------------------+
| Your Details                                                 |
+--------------------------------------------------------------+
| Collection or Delivery                                       |
+--------------------------------------------------------------+
| Payment explanation                                          |
+--------------------------------------------------------------+
| Check Your Order                                             |
| Privacy + human confirmation                                 |
|                                      [ Submit Kit Order ]     |
+--------------------------------------------------------------+
```

## 17. Navigation

The permanent URL should remain `/kit/` so it can be safely shared with members through WhatsApp regardless of later navigation changes.

Add **Kit** to the top-level navigation if it remains uncluttered; otherwise link it from an appropriate member-facing area.

## 18. Implementation Notes

- Reuse the shared HWFC header, footer, typography and form styling.
- Keep JavaScript progressive.
- Do not trust browser-submitted prices, entitlement flags or window state.
- Initials are captured for supplier fulfilment; the supplier applies them to the garment.
- Do not implement shirt-number capture.
- The free-shirt claim is recorded for administrative verification; no member-account or membership-database integration is required.
- Load kit-window settings, kit-manager email address and bank-transfer destination details from server-side configuration.
- Do not commit production bank details or the live kit-manager email address to the repository.
