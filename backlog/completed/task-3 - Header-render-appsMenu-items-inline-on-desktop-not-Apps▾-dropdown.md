---
id: TASK-3
title: 'Header: render appsMenu items inline on desktop (not Apps▾ dropdown)'
status: Done
assignee: []
created_date: '2026-04-21 19:23'
updated_date: '2026-04-21 19:48'
labels: []
dependencies: []
priority: high
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Rule §12 (updated 2026-04-21): appsMenu items must render as inline <a> links on desktop, exactly like appMenu items. Currently Header.php wraps all appsMenu in a named 'Apps ▾' header-dropdown (lines 146-163). Fix: iterate appsMenu items as flat links in the nav, same rendering path as appMenu. Children entries (Test submenu) stay as a dropdown. Mobile behaviour (hamburger drawer) is unchanged.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 appsMenu flat items render as inline links on desktop, no 'Apps' wrapper dropdown
- [ ] #2 appsMenu children entries (Test submenu) still render as a named dropdown on desktop
- [ ] #3 Mobile drawer: flat appsMenu items render as direct links; children entries as drill-down buttons with own dd-sub panels
- [ ] #4 No regression: appMenu items still render correctly
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
### Desktop nav (src/Header.php ~lines 146-163)

Replace the single "Apps▾" wrapper block with a per-item loop matching appMenu's pattern:

- For items without `children`: emit `<a href="...">label</a>` directly in `<nav class="header-nav">`
- For items with `children` (Test submenu): emit the same `<div class="header-dropdown">` pattern as appMenu, respecting `adminOnly`

### Mobile nav section (~lines 212-215)

Replace the single "Apps" drill-down button with per-item rendering:

- Flat items → `<a class="dropdown-link-btn">` directly in the nav section
- Children items → `<button class="dd-trigger" data-target="dd-sub-{slug}">` with slug derived from label (same `preg_replace` as appMenu)

### Mobile sub-panels (~lines 287-303)

Replace the monolithic `dd-sub-apps` panel with per-children-item panels (matching the appMenu loop above it):

- One `<div class="dd-sub" id="dd-sub-{slug}">` per children entry
- Back button: `← {item label}`
- Links: children hrefs as `<a class="dropdown-link-btn">`

### JS dropdown wiring (~line 357)

`$hasNavDropdown` currently tests only `!empty($appsMenu)`. Change to check whether any appMenu **or** appsMenu item has `children` — otherwise dropdown JS is emitted even when all appsMenu items are flat links.

### Verify

Test with wlmonitor locally (Test submenu in appsMenu). Confirm: flat jardyx.com links appear inline on desktop; Test dropdown appears as named dropdown; mobile shows flat links directly + Test drill-down panel.
<!-- SECTION:PLAN:END -->
