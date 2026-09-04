---
id: TASK-7
title: 'Header: Administration-Dropdown in Menüzeile, Usermenü bereinigen, statusHref'
status: Done
assignee: []
created_date: '2026-07-24 06:26'
updated_date: '2026-07-24 07:09'
labels: []
dependencies: []
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Suite-Policy §1/§2 / Design-Spec 2026-07-24. (1) Neue Option adminItems ({href,label}-Liste): Menüzeilen-Dropdown "Administration" mit "Verwaltung" (adminHref) als erstem Kind + adminItems; sichtbar wenn isAdmin ODER adminItems nicht leer (Apps filtern rollen-gated Items selbst, z.B. zeit-SAP-Import fuer secLevel>=2). (2) "Administration"-Eintrag aus dem User-Dropdown entfernen (Header.php:281-283). (3) Neue Option statusHref (Default base/status.php, null=aus): Eintrag "Status" im User-Dropdown fuer alle eingeloggten User, zwischen Anwendung und Theme-Pille. (4) Mobile: Administration als Drilldown in der Nav-Sektion spiegeln.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Administration erscheint als Menüzeilen-Dropdown, nicht mehr im User-Dropdown
- [ ] #2 adminItems-Only-Sichtbarkeit ohne Admin-Recht funktioniert (Rollen-Gate)
- [ ] #3 Status-Eintrag im Usermenü fuer alle eingeloggten User, per statusHref abschaltbar
- [ ] #4 Mobile-Drilldown fuer Administration vorhanden
<!-- AC:END -->
