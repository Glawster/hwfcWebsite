# HWFC Website – Contact Page Copy and Wireframe

Status: Draft for review  
Scope: Phase 1 Contact page

## 1. Page Goal

The Contact page should provide a simple, club-controlled way to contact HWFC without publishing personal committee email addresses or phone numbers.

Suggested URL:

`/contact/`

Primary route:

**Website contact form** → `info@hillsboroughwalkingfootball.com`

Secondary route:

**Facebook Messenger**

---

# 2. Recommended Page Order

```text
HEADER
  Logo
  Home | Play | Men's | Ladies' | About | Contact

PAGE INTRO
  Contact Hillsborough Walking Football Club
  Short guidance

CONTACT FORM
  Name
  Email
  Phone optional
  Enquiry type
  Message
  [Send Enquiry]

FIRST-TIME PLAYER PROMPT
  Contact beforehand
  Someone will be there to greet you

OTHER WAYS TO CONTACT US
  Facebook Messenger
  Official club email

PRIVACY NOTE
  Minimal, clear wording

FOOTER
```

---

# 3. Production Copy

## 3.1 Page Introduction

### Heading

**Contact Hillsborough Walking Football Club**

### Copy

Have a question about walking football, our sessions, fixtures or visiting HWFC? Send us a message and someone from the club will get back to you.

If you are thinking about playing, you do not need to commit to joining. Tell us which session interests you and we can help you get started.

---

# 4. Contact Form

## 4.1 Fields

### Name

Required.

### Email address

Required.

### Phone number

Optional.

### What are you contacting us about?

Required selection:

- **I'd like to try walking football**
- **Men's sessions**
- **Ladies' sessions**
- **Fixtures / visiting club**
- **General enquiry**

### Message

Required free-text field.

### Submit button

**Send Enquiry**

For recruitment-specific buttons elsewhere on the site, prefer:

**I'd Like to Try Walking Football**

---

# 5. Form Destination

All website form submissions should be delivered to:

`info@hillsboroughwalkingfootball.com`

This is the official public HWFC email identity.

The website should not reveal the personal email address or mobile number of the committee member who monitors enquiries.

The internal mailbox / forwarding arrangement can change without requiring any change to the public website.

---

# 6. First-Time Players

## Heading

**Coming along for the first time?**

### Copy

Get in touch with us beforehand and tell us which session you are interested in.

There is no special trial-session format. We will make sure someone is there to greet you, introduce you to the group and help you get settled into the session.

### Action

**See Session Details**

This links to `/play/`.

---

# 7. Other Ways to Contact HWFC

## Facebook Messenger

### Copy

If you already use Facebook, you can also message HWFC through our Facebook page.

### Action

**Message HWFC on Facebook**

Facebook URL:

`https://www.facebook.com/profile.php?id=100082995559544`

The Facebook Page should use an automatic first response to help categorise enquiries:

1. I'd like to try walking football.
2. Men's sessions.
3. Ladies' sessions.
4. Fixtures / visiting club.
5. General enquiry.

Do not rely on Facebook as the only public contact route.

## Email

Official club email:

`info@hillsboroughwalkingfootball.com`

The website may display this address as a fallback contact route, but the contact form should remain the main website action.

## WhatsApp

Do not publish a WhatsApp contact unless HWFC later establishes a dedicated club-controlled number.

A committee member's personal mobile number should not be used as the public HWFC WhatsApp contact.

---

# 8. Information Not to Request

The initial contact form should not request unnecessary or sensitive information such as:

- Date of birth.
- Home address.
- Medical information.
- Emergency contact details.
- Membership records.

If someone later joins or participates in an activity requiring additional information, that should be collected through the appropriate club process.

---

# 9. Privacy Copy

A short privacy statement should appear close to the form.

Suggested wording:

> We will use the information you provide only to respond to your enquiry and any related follow-up. We will not publish your details or share them outside HWFC unless required to deal with your request.

Do not add lengthy legal text directly beside the form. A fuller privacy page can be added separately if required.

---

# 10. Automated Email Acknowledgement

Where supported by the implementation, send an automatic acknowledgement from:

`info@hillsboroughwalkingfootball.com`

Suggested subject:

**We received your HWFC enquiry**

Suggested message:

> Thank you for contacting Hillsborough Walking Football Club.
>
> We have received your message and someone from the club will get back to you as soon as practical.
>
> If your enquiry is about trying walking football for the first time, we look forward to welcoming you.
>
> Hillsborough Walking Football Club

Avoid promising a specific response time unless the club is confident it can be maintained.

---

# 11. Technical Requirements for the Form

Implementation technology is not yet selected, but the final form should:

- Validate required fields on the server.
- Validate email format.
- Apply basic spam protection.
- Avoid exposing recipient email configuration in client-side code where practical.
- Avoid displaying submitted information publicly.
- Send submissions only to authorised club recipients.
- Avoid retaining submissions longer than needed unless there is a defined club requirement.
- Present a clear success message after submission.
- Present a useful error message if sending fails.
- Work well on mobile devices.

Avoid adding CAPTCHA that creates unnecessary difficulty for genuine users unless spam levels make it necessary.

---

# 12. Mobile Wireframe

```text
┌──────────────────────────────┐
│ LOGO                    MENU │
├──────────────────────────────┤
│ CONTACT HWFC                 │
│ Short introduction           │
├──────────────────────────────┤
│ NAME                         │
│ [________________________]   │
│                              │
│ EMAIL                        │
│ [________________________]   │
│                              │
│ PHONE (OPTIONAL)             │
│ [________________________]   │
│                              │
│ I'M CONTACTING ABOUT         │
│ [ Select...              v ] │
│                              │
│ MESSAGE                      │
│ [                        ]   │
│ [                        ]   │
│ [                        ]   │
│                              │
│ [ SEND ENQUIRY ]             │
│ Privacy note                 │
├──────────────────────────────┤
│ FIRST TIME?                  │
│ Contact beforehand and       │
│ someone will welcome you.    │
│ [ SESSION DETAILS ]          │
├──────────────────────────────┤
│ OTHER WAYS TO CONTACT        │
│ [ FACEBOOK MESSENGER ]       │
│ info@...                     │
├──────────────────────────────┤
│ FOOTER                       │
└──────────────────────────────┘
```

---

# 13. Contact Ownership Principle

All public contact mechanisms should belong to HWFC rather than to an individual member.

This allows committee responsibilities to change over time without having to replace personal contact information across the website, Facebook, printed material and QR-linked pages.
