---
id: TASK-MEDIUM.1
title: Migrate all 5 apps to Chrome library header/footer (complete the adoption)
status: To Do
assignee: []
created_date: '2026-04-19 05:47'
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
