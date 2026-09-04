---
id: TASK-5
title: 'Header: Cross-App-Links immer als Apps-Dropdown'
status: Done
assignee: []
created_date: '2026-07-24 06:26'
updated_date: '2026-07-24 07:09'
labels: []
dependencies: []
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Suite-Policy §1 / Design-Spec ~/Git/mcp/docs/superpowers/specs/2026-07-24-app-suite-menues-status-design.md. Die appsMenu-Plain-Links werden heute nur bei vorhandenem appMenu in ein Dropdown ("Links") kollabiert (Header.php:162-179); suche/simplechat/wlmonitor zeigen sie deshalb flach. Aendern: immer als Dropdown rendern, Label einheitlich "Apps" (wie die mobile Sektion).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Apps ohne appMenu zeigen die Cross-App-Links als Apps-Dropdown, nicht flach
- [ ] #2 Desktop-Label ist "Apps" (kein "Links" mehr)
<!-- AC:END -->
