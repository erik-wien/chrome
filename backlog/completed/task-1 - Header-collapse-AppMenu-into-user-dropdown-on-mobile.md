---
id: TASK-1
title: 'Header: collapse AppMenu into user dropdown on mobile'
status: Done
assignee: []
created_date: '2026-04-18 10:17'
updated_date: '2026-04-19 20:04'
labels: []
dependencies: []
priority: high
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
On small viewports the AppMenu (e.g. Energie's Aktuell / Monatlich / Jahre / Rechnung) should disappear from the header and its items should render inside the existing user dropdown, so the avatar acts as both "user menu" and "hamburger." Apps with no AppMenu (most) are unchanged on mobile — same user menu as today.

## Why now

- Mobile headers are tight: logo + appname (see css_library TASK-1) + nav + user = too many things. We already mandate §12's "user area always visible"; folding the nav under it means a single affordance on mobile.
- Avoids introducing a second hamburger widget. One dropdown, two sections.
- All apps share `chrome`, so a library-level change gives every app the new behaviour on next `composer update`.

## Header::render() changes (~/Git/chrome/src/Header.php)

1. Keep emitting `<nav class="header-nav">` for desktop exactly as today — do not gate it with JS/viewport checks.
2. When rendering the user dropdown, if `$appMenu` is non-empty, emit a new **Navigation** section at the top of `.user-dropdown` before `.dropdown-username`:

   ```html
   <div class="dropdown-section dropdown-nav-section">
     <span class="dropdown-section-label">Navigation</span>
     <a href="…" class="dropdown-link-btn">Aktuell</a>
     <a href="…" class="dropdown-link-btn active">Monatlich</a>
     …
   </div>
   <div class="dropdown-divider"></div>
   <!-- existing dropdown-username + Einstellungen + … -->
   ```

   The active page (match by `type === pageType`) gets `.active` — same rule as `.header-nav`.
   CSS hides this section on ≥768px (desktop keeps the horizontal nav) and shows it on ≤767px (where `.header-nav` is hidden). See companion css_library task.

3. The nav section is emitted **always** when `$appMenu` is non-empty — duplicated DOM, CSS toggles visibility. Simpler than a JS breakpoint check and no flash-of-wrong-layout on load.
4. If `$appMenu` is empty, the dropdown renders exactly as today — no empty section, no divider.

## Behavioural invariants

- Dropdown trigger button emits both `aria-label="Menü"` and the user's name as before. Screen readers on mobile announce "Menü, Erik, button" — acceptable (the user is also visible in the panel header).
- Clicking a nav link closes the dropdown (standard `<a>` navigation does this implicitly — full page load).
- Active-page highlight inside the dropdown mirrors the header-nav's: same `.active` class, CSS gives it accent-coloured text or a leading bar.
- Keyboard: first Tab from the user-btn lands on the first nav link in the dropdown (top of panel), matching visual order.

## Hook point for chrome users

No new API surface needed — existing `$cfg['appMenu']` already carries the items. Documentation update in chrome/CLAUDE.md: note that appMenu items render in both `.header-nav` (desktop) and `.user-dropdown .dropdown-nav-section` (mobile).

## Dependencies

Depends on css_library companion task (responsive rules + section styling). Chrome changes alone are safe to ship — without the CSS, the nav simply appears twice on mobile (ugly but not broken). Ship together to avoid that transient.

## Out of scope

- Changing the appMenu data shape (`{href,label,type}` stays).
- JS-driven collapse with a resize listener — CSS media queries handle it.
- Any change to apps that don't pass appMenu (they remain pure user-menu apps).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 `.user-dropdown` emits a `.dropdown-nav-section` at the top containing appMenu items when `$appMenu` is non-empty; otherwise unchanged
- [x] #2 Active page inside the dropdown section gets `.active` using the same type===pageType match as `.header-nav`
- [x] #3 `.header-nav` markup still emitted as today — responsive visibility handled purely by shared CSS
- [x] #4 Dropdown trigger button exposes `aria-label="Menü"` so it reads as a menu on mobile
- [x] #5 Chrome CLAUDE.md documents that appMenu items render twice (header-nav + dropdown) and CSS owns which is visible
- [x] #6 No app consumer code changes required — Energie, wlmonitor, zeiterfassung, simplechat, suche all pick up the behaviour via composer update
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced the old accordion (`.dd-acc*`) + split-panel (`.dd-mobile-panel`/`.dd-desktop-panel`) pattern with a unified `.dd-main`/`.dd-sub` drill-down system.

**chrome/src/Header.php**
- Removed `$chevD` / accordion HTML; added `$chevR`/`$chevL` chevrons
- `.dd-main` wraps all primary dropdown content
- `.dropdown-nav-section` + `.dropdown-section-label` emitted at top of `.dd-main` when `$appMenu` non-empty (mobile only via CSS)
- Active page match (type===pageType) applied inside nav section
- `.dd-mobile` "Konto" trigger + `.dd-desktop` direct account links (CSS owns visibility)
- `.dd-sub` panels for each `children` group + `dd-sub-konto`
- JS: replaced `collapseAcc`/`.dd-acc-header` handlers with `resetDd`/`.dd-trigger`/`.dd-back` handlers
- Added `aria-label="Menü"` to `.user-btn`

**css_library/layout.css**
- Removed old `.dd-mobile-panel`, `.dd-desktop-panel`, `.dd-acc*` rules
- Added: `.dd-main.dd-collapsed`, `.dd-sub`, `.dd-sub.dd-open`, `.dd-mobile`/`.dd-desktop` breakpoint rules, `.dropdown-nav-section` (hidden ≥768px), `.dropdown-section-label`, `.dropdown-nav-section a.active`, `.dd-chevron-btn`

**chrome/CLAUDE.md** — `appMenu` entry updated to document dual-render and new CSS classes table expanded.
<!-- SECTION:FINAL_SUMMARY:END -->
