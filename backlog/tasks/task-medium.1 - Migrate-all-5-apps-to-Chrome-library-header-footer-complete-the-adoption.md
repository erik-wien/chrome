---
id: TASK-MEDIUM.1
title: Migrate all 5 apps to Chrome library header/footer (complete the adoption)
status: Done
assignee: []
created_date: '2026-04-19 05:47'
updated_date: '2026-04-21 16:57'
labels: []
dependencies: []
parent_task_id: TASK-MEDIUM
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Cross-app audit (2026-04-19) measured Chrome-library adoption as: Energie 1/19 pages, wlmonitor 1/19, zeiterfassung 0/14, simplechat 0/18, suche 0/15. Every app still hand-rolls header and footer in `inc/_header.php` + `inc/_footer.php`. §12/13 invariants (dropdown contents, fixed layout, version format, avatar fallback, theme row) drift on every spec change because there are five copies to keep in sync.

Scope — migrate every page in every app to:

    \Erikr\Chrome\Header::render([...])
    \Erikr\Chrome\Footer::render([...])

and delete the per-app `_header.php` / `_footer.php`. Per-app branding (logo, app-name, nav items, prefs/security/help/logout hrefs) passed via args.

Per-app checklist (one sub-commit each, independently deployable):
- [ ] Energie: migrate remaining 18 pages, delete `inc/_header.php` / `inc/_footer.php`
- [ ] wlmonitor: migrate remaining 18 pages, same cleanup
- [ ] zeiterfassung: migrate 14 pages, delete hand-rolled header (drops emoji theme icons, adds Pw&2FA / Help / Admin entries — depends on the zeiterfassung auth-parity task)
- [ ] simplechat: migrate 18 pages, delete hand-rolled header (handle German filenames via href args)
- [ ] suche: migrate 15 pages + fix footer version format (§13) as part of the move

Rationale: consistency pays off only when ≥90% adopted. Five half-migrated apps is worse than the pre-migration state because the divergence is harder to spot.

Depends on: zeiterfassung auth-parity task (don't migrate zeit until it has the full §12 menu entries to route to). Subsumes the suche footer-format task if done together.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Every page in all 5 apps uses Chrome\Header::render() and Chrome\Footer::render()
- [ ] #2 Per-app inc/_header.php and inc/_footer.php deleted (no dead copies left)
- [ ] #3 Dropdown contents, theme row, avatar fallback, footer version format identical across all apps (diff-check one example page per app)
- [ ] #4 No regression in mobile nav / hamburger behaviour
- [ ] #5 Deploy smoke-test on all 5 apps confirms header + footer render and logout still works
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
Umbrella task — 5 apps × ~18 pages average = ~85 page migrations. Do one app end-to-end per session; avoid cross-app mixing (backlog "one task per session" rule).

Dependency: zeiterfassung must have `task-high.1` (auth parity — Pw&2FA page, Help page, full §12 menu) already merged before zeiterfassung migration. Per backlog, that task is already Done.

**Per-app pattern** (identical across all 5):
1. Read `inc/_header.php` or equivalent — identify every value passed to inline header markup (appName, navigation items, brand href, avatar endpoint, theme endpoint).
2. Build a `$chromeHeader` config dict once in `inc/initialize.php`:
   ```php
   $chromeHeader = [
     'appName' => 'AppName',
     'base' => $base,
     'cspNonce' => $_cspNonce,
     'csrfToken' => csrf_token(),
     'appMenu' => [...],
     'brandLogoSrc' => $base.'/icons/jardyx.svg',
     // etc.
   ];
   ```
3. Per page: replace `include 'inc/_header.php'` with `\Erikr\Chrome\Header::render(array_merge($chromeHeader, ['pageType' => 'foo']));` and same for Footer.
4. Delete `inc/_header.php` and `inc/_footer.php` once all pages are migrated — grep first to confirm zero references.
5. Smoke-test every page locally.

**App order (most-isolated first, biggest-risk last):**
1. **suche** (15 pages — simplest) — also fold in suche's footer-format fix (§13).
2. **Energie** (19 pages, 18 remaining) — PHP + database, no frameset weirdness.
3. **wlmonitor** (19 pages, 18 remaining) — handle `html_header.php`/`html_footer.php` split; verify apps-menu nav items render correctly.
4. **simplechat** (18 pages) — German filenames (`einstellungen.php`, `sicherheit.php`) — pass custom hrefs via `prefsHref`/`securityHref`; handle `inc/html.php` `topBar()` / `standaloneTopBar()` which live in a shared include.
5. **zeiterfassung** (14 pages) — hand-written header has pre-auth pages (login, forgotPassword, totp_verify, setpassword, executeReset); those need Chrome with `loggedIn=false`.

**Per-app sub-tasks:** track in each app's backlog (they exist: simplechat MEDIUM.2, zeiterfassung MEDIUM.3). Mark this umbrella Done only when all five app tasks are Done.

**Verification (AC #3):** diff the rendered HTML of one representative page per app — dropdown contents, theme row, avatar fallback, footer version must be character-identical (modulo app-name).
<!-- SECTION:PLAN:END -->
