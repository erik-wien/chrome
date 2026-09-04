---
id: TASK-6
title: 'AppsMenu: Test-Submenü (*.test) entfernen'
status: Done
assignee: []
created_date: '2026-07-24 06:26'
updated_date: '2026-07-24 07:09'
labels: []
dependencies: []
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Suite-Policy §1: keine Dev-/Test-Links im Apps-Menü — auch nicht lokal/admin-only. Den env===local-Block in AppsMenu::build() (AppsMenu.php:65-74) ersatzlos entfernen; $env-Parameter signaturkompatibel belassen (deprecated-Kommentar).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Kein Test-Submenü mehr, in keinem Env
- [ ] #2 Bestehende Aufrufe AppsMenu::build(key, APP_ENV) laufen unveraendert
<!-- AC:END -->
