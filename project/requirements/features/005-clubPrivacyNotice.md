# 005: Club privacy notice

## Status

Completed

## Outcome

As a `website visitor, prospective member, member or club administrator`, I need HWFC to publish clear and accessible privacy information so that people understand what personal information the club collects, why it is used, how it is protected and retained, who it may be shared with, and what rights they have.

## Context

The HWFC website already collects or is expected to collect personal information through facilities including the public contact form, membership applications, member administration, kit ordering and related club administration.

REQ-004 Membership Application and Management requires the membership application to present the current privacy information and record the applicable privacy-notice version at the time of submission. A single, maintained club privacy notice is therefore required as an authoritative source for this information.

The privacy notice must reflect the club's actual processing rather than being generic website boilerplate. It must be written in clear language suitable for ordinary club members and website visitors and must remain maintainable as website functions and club processes change.

The notice should support HWFC's obligations under applicable UK data-protection law and guidance, including transparency about the purposes and lawful bases for processing, retention, sharing, individual rights and routes for raising concerns.

## Scope

- Provide a publicly accessible Club Privacy Notice on the HWFC website.
- Give the notice a stable URL suitable for linking from forms and the website footer, for example `/privacy/`.
- Include a persistent link to the Privacy Notice from the website footer or equivalent site-wide navigation.
- Identify the organisation responsible for the personal information and provide an appropriate club-controlled contact route for privacy enquiries.
- Explain, in plain language, the categories of personal information HWFC may collect, including where applicable:
  - names;
  - postal addresses;
  - email addresses;
  - telephone numbers;
  - dates of birth;
  - emergency-contact details;
  - membership identifiers, status and expiry information;
  - membership payment records;
  - kit-order information;
  - website contact/enquiry information;
  - optional member photographs;
  - event, match and club photographs or media where applicable;
  - technical website information such as logs, cookies or analytics where those facilities are actually used.
- Explain the purposes for which each relevant category of information is used.
- State the applicable lawful basis or bases for the club's processing in a way that reflects the club's approved data-protection position.
- Distinguish processing necessary to administer membership or respond to enquiries from genuinely optional consent-based processing.
- Do not use a blanket statement that a person must "consent to GDPR" in order to use ordinary club services.
- Explain where personal information comes from where it may be obtained from somewhere other than the individual concerned.
- Explain the types of recipients with whom personal information may be shared where applicable, without implying wider sharing than actually occurs.
- Explain the club's approach to personal-data retention and that information will not be kept indefinitely without a defined purpose or requirement.
- Explain relevant individual rights, including access and correction and, where applicable, deletion, restriction, objection and other rights provided by applicable law.
- Explain how an individual can raise a privacy concern with HWFC and how to contact the Information Commissioner's Office where appropriate.
- Explain any relevant international transfer arrangements if HWFC later uses services that process personal information outside the UK; do not include speculative transfer wording where no such processing exists.
- Explain the use of cookies or analytics where those technologies are actually enabled, or link to a separate cookie notice where the implementation warrants one.
- Give the Privacy Notice a visible version or effective date so that material changes can be identified.
- Maintain previous privacy-notice versions, or sufficient version history, where required to demonstrate what information was presented to a person at the time their data was collected.
- Make the current privacy-notice version available to application code so forms that require it can record the applicable version with the submission.
- Link to the Privacy Notice at the point where personal information is collected rather than relying only on the footer link.
- On the public contact form, present a concise privacy statement explaining that submitted details will be used to respond to the enquiry and related follow-up, with a link to the full Privacy Notice.
- On the membership application, present a concise privacy statement and a link to the full Privacy Notice before submission.
- On the kit-order form and any future form collecting personal information, present proportionate privacy information and a link to the full Privacy Notice where required.
- Ensure privacy information is readable on mobile devices and does not require authentication.
- Ensure the privacy page is accessible using the same accessibility standards as other public website content.
- Keep the notice content separate from application logic so authorised maintainers can update wording without changing unrelated business logic.
- Review the notice whenever a feature begins collecting a materially new category of personal information or using existing personal information for a materially new purpose.

## Out of scope

- Providing legal advice to individual members.
- Creating a generic copied privacy policy that does not describe HWFC's real processing.
- Requiring consent for processing that relies on another lawful basis.
- A standalone cookie-consent platform unless required by the technologies actually deployed on the website.
- Publishing private member information as part of the privacy notice.
- Replacing the operational access controls, retention mechanisms, subject-access support or deletion/anonymisation controls required by REQ-004.

## Acceptance criteria

1. Given any public website visitor, when they use the site, then they can reach the current Club Privacy Notice from a persistent site-wide link without authenticating.
2. Given the Privacy Notice page, when it is viewed, then it identifies HWFC, provides an appropriate club-controlled privacy contact route and displays a current version or effective date.
3. Given the website's current personal-data processing, when the Privacy Notice is reviewed, then it describes the categories of information actually collected, the purposes for which they are used and the applicable lawful basis or bases.
4. Given a membership applicant, when they complete the membership application, then a concise privacy statement and link to the full Privacy Notice are presented before submission.
5. Given a valid membership application, when it is stored under REQ-004, then the privacy-notice version recorded with the application corresponds to the notice version presented at the time of submission.
6. Given a person using the public contact form, when they enter personal information, then they are shown concise privacy information explaining the use of their enquiry details and are able to open the full Privacy Notice.
7. Given a person placing a kit order or using another website form that collects personal information, when the form requires privacy information, then an appropriate short notice and link to the full Privacy Notice are presented at the point of collection.
8. Given the Privacy Notice, when retention is described, then it does not state that personal information is kept indefinitely by default and reflects the club's approved retention approach.
9. Given the Privacy Notice, when individual rights are described, then it explains how a person can request access or correction and includes the other rights applicable to the club's processing.
10. Given a privacy concern, when a visitor reads the Privacy Notice, then they can identify how to contact HWFC and how to escalate a concern to the Information Commissioner's Office where appropriate.
11. Given optional processing based on consent, when it is described, then it is clearly distinguishable from information the club processes to administer membership, orders, enquiries or other necessary club functions.
12. Given a mobile viewport, when the Privacy Notice is viewed, then the content remains readable and usable without requiring a desktop layout.
13. Given a material change to the Privacy Notice, when the new version is published, then the previous version or sufficient version history remains available for audit purposes where existing records refer to it.
14. Given a new website feature that collects a materially new category of personal information or introduces a materially new purpose, when the feature is prepared for production, then review and update of the Privacy Notice is included in the release work.

## Dependencies and decisions

- REQ-004 Membership Application and Management depends on a maintained privacy notice and must record the privacy-notice version presented with each membership application.
- REQ-001 Member Kit Ordering and the public contact form must link to the notice where they collect personal information.
- The authoritative privacy contact should use a club-controlled address or contact route rather than publishing a committee member's private contact details.
- The precise lawful bases, retention periods and sharing arrangements must reflect HWFC's approved operational practice and should be reviewed before production publication.
- The Privacy Notice should be normal web content rather than only a downloadable document so it remains accessible, searchable and easy to link from forms.
- A separate cookie notice may be introduced later if the website technologies used make that clearer or necessary; this requirement does not assume that one is required before those technologies are selected.

## Verification

- Manual review confirming the privacy page is publicly accessible from the site-wide footer or equivalent persistent navigation.
- Link tests confirming contact, membership and applicable order forms link to the current Privacy Notice.
- Integration test proving REQ-004 records the current privacy-notice version with membership submissions.
- Content review against the website's actual data fields and processing before production release.
- Review of lawful-basis, retention, data-sharing and individual-rights wording against the club's approved privacy practices before production release.
- Responsive and accessibility checks for the Privacy Notice page on common desktop and mobile viewport sizes.
- Regression check whenever a new feature introduces new personal-data collection or a materially new processing purpose.

## Change history

- 2026-10-25: created — require a public, maintained Club Privacy Notice with point-of-collection links, versioning, membership integration and coverage of HWFC's website and club-administration processing.
- 2026-10-27: status ToDo -> InProgress — myBoard.
- 2026-10-27: status InProgress -> Completed — myBoard.
