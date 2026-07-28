---
id: TASK-10
title: >-
  Status: echten SMTP-Erreichbarkeitscheck in Chrome\Status ergaenzen
  (suite-weite Luecke)
status: To Do
assignee: []
created_date: '2026-07-28 08:56'
labels: []
dependencies: []
priority: medium
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Verifizierter Befund aus dem Statusseiten-Audit (2026-07-28). Library-first, weil er alle sieben Apps gleichzeitig betrifft (Auth-Rules §1, ~/Git/CLAUDE.md: geteilte Bibliothek = globaler Task).

BEFUND: Alle sieben Apps der Suite versenden Mail - Einladungen, Passwort-Resets, E-Mail-Aenderungsbestaetigungen - ausschliesslich ueber Erikr\Auth\Mail\smtp_send() (auth/src/mailer.php:77-105, PHPMailer/SMTP), ausgeloest u.a. aus admin_create_user()/admin_reset_password() ueber chrome/src/Admin/Dispatch.php:116,177, also aus jeder web/admin.php, sowie aus profil.php, forgotPassword.php und setpassword.php. KEINE EINZIGE APP prueft, ob dieser Weg funktioniert.

Bestandsaufnahme aller sieben status.php:
  suche, biblio, Energie, wlmonitor, simplechat, last.fm  -> gar kein Mail-Check
  zeiterfassung                                           -> ein Check namens 'SMTP-Konfiguration'

Und selbst der zeiterfassung-Check misst nichts Netzseitiges: er ruft load_mail_config() (auth/src/mailer.php:33-41), das eine INI vom lokalen Dateisystem liest und prueft, ob host/port/user/password gesetzt sind. Kein Connect, kein Handshake. Er ist gruen, waehrend der Mailserver down, das Passwort falsch oder der Port geblockt ist.

WARUM DAS ZAEHLT: Ein SMTP-Ausfall ist genau die Sorte Stoerung, die niemand bemerkt. Nichts crasht, keine Seite wird rot - es kommt nur schlicht keine Einladung und kein Passwort-Reset mehr an, und der betroffene Nutzer sitzt ausgesperrt davor. Erst eine Ampel macht das sichtbar.

UMSETZUNGSHINWEIS: Der Check darf keine Mail versenden. Ein TCP-Connect plus SMTP-Banner (220) und optional EHLO/STARTTLS gegen den konfigurierten Host/Port reicht als Lebenszeichen und ist nebenwirkungsfrei. Suite-Policy §5 gilt: Timeout <= 3 s, Hostnamen und Fehlertexte nur fuer Admins, Ergebnis ~60 s gecacht. Ein LOGIN-Versuch mit echten Zugangsdaten ist zu erwaegen (nur so faellt ein falsches Passwort auf), aber gegen das Risiko von Fail2ban-/Rate-Limit-Sperren des eigenen Mailkontos abzuwaegen - die Entscheidung gehoert im Code begruendet.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Chrome\Status bietet einen wiederverwendbaren SMTP-Erreichbarkeitscheck, der die Mail-Konfiguration aus erikr/auth nutzt und keine Mail versendet
- [ ] #2 Der Check unterscheidet 'nicht konfiguriert' (gelb) von 'konfiguriert, aber Server antwortet nicht' (rot) und nennt im Fehlerfall die konkrete Ursache (§21), Hostnamen nur fuer Admins
- [ ] #3 Timeout <= 3 s, sodass eine haengende Mailserver-Verbindung die Statusseite nicht blockiert
- [ ] #4 Ob zusaetzlich ein LOGIN geprueft wird, ist entschieden und im Code begruendet - inklusive der Abwaegung gegen Sperrmechanismen des Mailservers
- [ ] #5 Der Check ist in mindestens einer App eingesetzt und dort gegen einen real nicht erreichbaren Host verifiziert
<!-- AC:END -->
