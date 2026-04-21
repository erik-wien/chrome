---
id: TASK-HIGH.1
title: >-
  Header::render() — user dropdown: add Benutzereinstellungen group + Anwendung
  slot
status: Done
assignee: []
created_date: '2026-04-21 16:25'
updated_date: '2026-04-21 17:02'
labels: []
dependencies: []
parent_task_id: TASK-HIGH
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The desired user dropdown schema has three structural changes vs current:
1. Benutzereinstellungen group (with profileHref, emailHref, securityHref as sub-items)
2. Anwendung slot (new param appPrefsHref + appPrefsLabel for app-specific settings link)
3. Group separator/label between Benutzereinstellungen and Design/Hilfe/Logout

Currently Header::render() emits a flat list: Einstellungen, Passwort&2FA, Admin, Help, Design, Logout.
Target: grouped dropdown with Benutzereinstellungen (Profilbild, E-Mail, Sicherheit), Anwendung, Design, Hilfe, Logout.

Add new params: profileHref, emailHref (currently missing), appPrefsHref, appPrefsLabel.
Keep existing prefsHref/securityHref as aliases/fallbacks for backward compat.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 New params profileHref, emailHref, appPrefsHref, appPrefsLabel accepted by Header::render()
- [x] #2 User dropdown renders Benutzereinstellungen group with sub-items when hrefs provided
- [x] #3 Anwendung item appears between Benutzereinstellungen and Design when appPrefsHref set
- [x] #4 Existing apps with only prefsHref/securityHref still render correctly (no regression)
<!-- AC:END -->
