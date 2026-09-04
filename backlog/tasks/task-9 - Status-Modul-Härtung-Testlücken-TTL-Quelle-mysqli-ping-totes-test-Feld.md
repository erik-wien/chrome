---
id: TASK-9
title: 'Status-Modul-Härtung: Testlücken, TTL-Quelle, mysqli::ping, totes test-Feld'
status: Done
assignee: []
created_date: '2026-07-24 08:40'
updated_date: '2026-07-24 11:42'
labels: []
dependencies: []
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Final-Review Suite-Umbau 2026-07-24 (Low-Sammel): (1) Tests ergaenzen: kaputtes/unlesbares Cache-JSON -> Re-Run statt Fatal; State-Normalisierung ungueltiger Werte -> fail. (2) TTL-Pruefung von filemtime auf generated_ts im Cache-Inhalt umstellen. (3) Docblock-Beispiel: mysqli::ping() ist deprecated ab PHP 8.4 -> Beispiel auf Query-Ping (SELECT 1) umstellen; Konsumenten (suche/wlmonitor/last.fm/simplechat status.php) folgen. (4) AppsMenu::APPS[*][test]-Feld ist seit TASK-6 tot -> entfernen. (5) Hinweis fuer Konsumenten: redundante Session-Checks im format=json-Zweig (suche/zeit/simplechat) bei Gelegenheit entfernen.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Neue Tests gruen
- [ ] #2 Kein mysqli::ping mehr im Docblock
<!-- AC:END -->
