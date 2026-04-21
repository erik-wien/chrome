---
id: TASK-MEDIUM.2
title: 'Header::render() — add named appsMenu parameter separate from appMenu'
status: Done
assignee: []
created_date: '2026-04-21 16:25'
updated_date: '2026-04-21 17:07'
labels: []
dependencies: []
parent_task_id: TASK-MEDIUM
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Apps navigation (links to other apps) and app-specific nav (Buchen/Archiv/Ampel etc.) are currently both passed via appMenu, making them visually identical. The desired schema has them as distinct clusters.

Add an appsMenu parameter that renders as a named 'Apps' dropdown in the header-right cluster, distinct from the primary appMenu items. All apps should pass their cross-app links via appsMenu (excluding self) and app-specific page links via appMenu.

Test submenu should also move to appsMenu as a children entry.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 appsMenu parameter accepted by Header::render()
- [x] #2 appsMenu renders as distinct 'Apps' dropdown cluster in header-right
- [x] #3 Test submenu passed as children entry in appsMenu, not appMenu
- [x] #4 Apps without appsMenu still render correctly
<!-- AC:END -->
