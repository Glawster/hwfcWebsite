<!-- deployed from Glawster/organiseMyProjects release 0.9.1 -- do not edit directly -->
# Shared TUI styling

## Canonical theme and adoption

`organiseMyProjects/myStyles.css` is the canonical Textual theme. Project creation
and update deploy it as `myStyles.css` at the selected project root. Load it using
Textual's `App.CSS_PATH`, resolving the path from the application module rather
than relying on the working directory. Updates refresh OMP-marked copies, preserve
unmarked application-owned stylesheets, and support dry-run. An unmarked exact
copy of the canonical theme can be adopted automatically. To adopt from a custom
theme, review and replace it with the canonical theme first; update adds provenance.
`clannEolas` is exempt and its stylesheet is never deployed or refreshed.

An application that deliberately owns its Textual theme must place it at
`styles/projectStyles.css` and load that path through `App.CSS_PATH`. This path
is the sole project-specific override location: do not use a root-level
`myStyles.css` as an override, because that name is reserved for the deployed
shared theme.

## Where colour lives

`myStyles.css` owns colour: the canvas, surfaces, the cursor, and the `Label`
and `Static` ink on those surfaces. Its leading Textual `$` variables are the
single source for palette values; component rules must use semantic variables
rather than repeat hex values. Application CSS owns layout only: size, padding,
alignment, and which regions use
`classes="surface"`.

The light canvas is the chrome: the screen, header, footer, tabs, and a one-line
status strip. The working frame is the dark surface. Panels, columns and dialogs
use the neutral border. Tables, lists and entry controls use the selected-list
text colour for their border. A highlighted list row uses the same cursor
colours as a table, and the label inside that row uses the cursor text colour.

Scrollbars use the muted canvas colour for their thumb, becoming lighter on
hover and active drag, against the dark-surface track.

A dark surface sets the colour of its own `Label` and `Static` children. A colour
on the container does not reach them. Detail headings and commit hashes use the
accent on the light canvas. Each changed file is its own line.

## Buttons and groups

Standard buttons occupy exactly one terminal row, with no border and no vertical
padding. Horizontal padding leaves labels readable. Hover, focus, active, disabled
and semantic variants retain this geometry. Standard actions use the shared green
accent and dark foreground; success, warning and error variants use the shared
semantic palette.

Plain `Horizontal` button rows receive a one-column gap automatically, with no
trailing gap after the final button. Use `classes="button-group"` to also apply
equal widths and surrounding vertical spacing:

```python
with Horizontal(classes="button-group"):
    yield Button("Run", variant="primary")
    yield Button("Cancel")
```

The group gives buttons equal fractional widths, a one-column gap between
neighbours, and one row above and below the group. Integer terminal widths may
require a one-column width difference when the available columns cannot divide
evenly. Put the group inside the content region so its vertical margins separate
it from surrounding content and frame boundaries. Choose labels that fit the
available width; use a vertical layout for very narrow displays.

Remove screen-specific height, border, padding and palette overrides that merely
reproduce this convention. Keep deliberate semantic variants. For `mailAgent`,
load the deployed shared theme, add `button-group` to execution controls, and
remove execution-screen button height/border overrides. A downstream rendering
check is required before that application's adoption is complete.

## Checks and suite impact

`tests/test_sharedTuiStyles.py` exercises the real Textual renderer at multiple
terminal sizes and checks content height, label rendering, interaction states,
group widths and spacing. Deployment tests cover dry-run, refresh, idempotency
and project-owned stylesheet preservation. Textual is a development dependency;
OMP core remains independent of it.

OMS, OMV and OMP can adopt the theme if they introduce conforming Textual UIs;
their Qt/Tkinter interfaces need no changes. OMPR owns the canonical theme and
its deployment. Existing downstream custom themes require explicit adoption.

## SVG mockup

Generate a reproducible rendering of the shared theme's ordinary panels, list,
table, inputs, buttons and semantic states:

```bash
styleMockup
```

The generator finds the nearest Git repository root and writes its output there,
derived from the selected stylesheet name. It uses
`styles/projectStyles.css` when present, producing `output/projectStyles.svg`;
otherwise it uses OMP's canonical stylesheet and produces
`output/myStyles.svg`. The generator runs Textual headlessly at a fixed size, so
re-running it after a stylesheet change produces a directly comparable SVG
without opening a terminal UI.
