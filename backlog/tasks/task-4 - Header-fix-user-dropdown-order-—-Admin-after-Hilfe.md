---
id: TASK-4
title: 'Header: fix user dropdown order — Admin immediately above Design'
status: To Do
assignee: []
created_date: '2026-04-21 19:23'
updated_date: '2026-04-21 20:00'
labels: []
dependencies: []
priority: medium
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Rule §12 user dropdown order (mandatory): Benutzereinstellungen group (Profilbild, E-Mail) → Sicherheit (top-level, NOT inside the group) → Anwendung → **Admin** → **Design** → Hilfe → Logout. Current Header.php grouped mode: (1) puts Sicherheit inside the Benutzereinstellungen group block; (2) has Admin before Design (coincidentally correct placement but wrong position relative to Hilfe). Fix: extract securityHref render out of the group block; confirm Admin renders after Anwendung and before Design; Hilfe goes after Design before Logout.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Sicherheit renders as top-level dropdown item, not inside Benutzereinstellungen group
- [ ] #2 Full order: Benutzereinstellungen (Profilbild, E-Mail) → Sicherheit → Anwendung → Admin → Design → Hilfe → Logout
- [ ] #3 Admin appears immediately above the Design/theme-switcher block
- [ ] #4 Hilfe appears after Design, before Logout
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Open `src/Header.php`; find the grouped-mode user dropdown render block (search for `profileHref` / `Benutzereinstellungen`)
2. Locate the `securityHref` emit inside the group block — move it to its own `<li>` immediately after the closing `</ul>` of the Benutzereinstellungen group, before the `appPrefsHref` block
3. Verify render order matches: group → securityHref li → appPrefsHref li → adminHref li → theme-switcher li → helpHref li → logout form
4. Test by running a consumer app locally (wlmonitor or Energie) with an admin user — open user dropdown, confirm visual order matches §12
<!-- SECTION:PLAN:END -->
