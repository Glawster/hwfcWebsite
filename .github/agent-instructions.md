# Agent instructions

Read `documentation/requirementsManagement.md`.

For requirement work:

- use `project/requirements/requirementsIndex.md` as the next-ID authority;
- keep authoritative requirements in `project/requirements/features/`;
- keep matching durable implementation prompts in `project/requirements/prompt/`;
- record transient implementation status only in `project/currentIncrement.md`;
- use `project/adr/` for consequential architecture or project-shaping decisions;
- do not create requirement copies in `documentation/`.

Durable product copy, UX guidance and technical explanations belong in `documentation/` and should link back to the relevant requirement rather than duplicating it.
