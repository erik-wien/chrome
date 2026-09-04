---
id: TASK-8
title: Neues Modul Chrome\Status (Status-Seiten-Renderer + JSON)
status: Done
assignee: []
created_date: '2026-07-24 06:26'
updated_date: '2026-07-24 07:19'
labels: []
dependencies: []
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Suite-Policy §5 / Design-Spec 2026-07-24. Status::render(checks, isAdmin) + Status::run(checks). Check = {name, check-Callable -> ok|warn|fail + detail? + last_success_ts?, adminOnly?}. Ampel + letzter-Erfolg-Zeitstempel fuer alle User; detail nur fuer Admins. Ergebnis-Cache ~60s (Datei in data/), externe HTTP-Checks Timeout <=3s, HTTP>=400=Fehler (§21). status.php?format=json liefert {app, generated_ts, checks[{name,state,last_success_ts}]} ohne detail; optional Token-Zugriff (status_token) fuer Dashboard-Aggregation.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Renderer mit Ampel + Zeitstempel, Details admin-only
- [ ] #2 Cache und Timeouts implementiert
- [ ] #3 JSON-Format ohne Interna, Session- oder Token-Auth
<!-- AC:END -->
