---
id: TASK-11
title: 'Suite-Sweep: mysqli::ping() in vier Statusseiten ersetzen (PHP 8.4 deprecated)'
status: Done
assignee: []
created_date: '2026-07-28 18:17'
updated_date: '2026-07-28 20:21'
labels: []
dependencies: []
priority: low
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Nebenbefund aus dem Statusseiten-Audit (2026-07-28). Kein chrome-Code betroffen — der Task liegt hier, weil er vier Apps gleichzeitig betrifft und es kein Root-Backlog in ~/Git gibt; die Aenderung selbst passiert in den Apps.

BEFUND: mysqli::ping() ist seit PHP 8.4 deprecated ('the reconnect feature has been removed in PHP 8.2 and this method is now redundant). Hamish UND akadbrain laufen beide PHP 8.5.8 — das ist also live, nicht theoretisch.

Betroffen sind die Statusseiten von vier Apps:
  suche/web/status.php
  simplechat/web/status.php
  last.fm/web/status.php
  wlmonitor/web/status.php

Alle vier rufen Status::dbCheck(fn() => $con->ping(), ...).

WARUM DAS ZAEHLT: Bei jedem Cache-Miss der Statusseite entsteht ein
Deprecated-Eintrag im Fehlerlog. Steht display_errors an, landet die Meldung
sogar mitten im Seiten-Output bzw. im JSON von ?format=json — genau das
Problem, das in Energie/web/status.php und inc/ai_client.php schon fuer
curl_close() dokumentiert ist. Eine Statusseite, die selbst Muell ins Log
schreibt, ist ein schlechtes Vorbild.

LOESUNG: $con->query('SELECT 1') !== false statt $con->ping(). Das ist nicht
nur deprecation-frei, sondern prueft mehr: ein echter Round-Trip statt eines
seit PHP 8.2 wirkungslosen Aufrufs. So bereits umgesetzt in Energie
(TASK-9) und biblio (TASK-37) — dort steht die Begruendung im Code.

Kein Library-Fix moeglich: Status::dbCheck nimmt eine Callable entgegen, die
Apps liefern den Ping selbst. Die Doku in chrome/CLAUDE.md nennt $con->ping()
allerdings als Beispiel — die Stelle gehoert mitgezogen, sonst breitet sich
das Muster in die naechste App aus.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Keine der vier Statusseiten ruft noch mysqli::ping(); alle nutzen einen echten Round-Trip
- [x] #2 Ein Aufruf jeder betroffenen Statusseite erzeugt keinen Deprecated-Eintrag mehr im Fehlerlog (auf PHP 8.5 geprueft)
- [x] #3 Das Beispiel in chrome/CLAUDE.md zeigt nicht laenger $con->ping()
<!-- AC:END -->
