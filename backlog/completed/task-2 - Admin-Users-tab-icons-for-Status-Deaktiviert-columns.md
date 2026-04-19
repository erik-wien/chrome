---
id: TASK-2
title: 'Admin Users tab: icons for Status + Deaktiviert columns'
status: Done
assignee: []
created_date: '2026-04-18 10:41'
updated_date: '2026-04-19 20:09'
labels: []
dependencies: []
priority: medium
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Replace the text badges in the `Status` and `Deaktiviert` columns of the admin users tab (`chrome/src/Admin/UsersTab.php`) with coloured icons. Keeps the table scannable at a glance, reduces column width, matches the existing `ui-icon` sprite pattern used elsewhere in chrome.

## State today

`chrome/src/Admin/UsersTab.php` lines 45–52, 107–111:

- **Status** column renders one of three text badges:
  - `activated` → green pill "aktiv"
  - `invite-pending` → amber pill "Einladung offen"
  - `reset-pending` → amber pill "Passwort-Reset offen"
- **Deaktiviert** column renders:
  - `disabled = 1` → red pill "ja"
  - `disabled = 0` → green pill "nein"

## Target

Bare coloured icon (no pill), one per cell, centred:

| State | Icon | Colour | SR / tooltip text |
|---|---|---|---|
| Active (Status = activated) | check | `--color-success` | "aktiv" |
| Invite pending | hourglass | `--color-warning` | "Einladung offen" |
| Password reset pending | hourglass | `--color-warning` | "Passwort-Reset offen" |
| Deaktiviert = ja | close (X) | `--color-danger` | "deaktiviert" |
| Deaktiviert = nein | check | `--color-success` | "aktiv" |

Reset-pending and invite-pending share the hourglass — they are both "waiting on user action" states and the distinction is already carried by the hover tooltip + SR label. If you want distinct icons later, add a dedicated reset icon (e.g. clock or key) without changing the table layout.

## Implementation

### 1. Add the hourglass icon

`~/Git/css_library/icons/icon-hourglass.svg` — monochrome SVG authored as a mask source (matches `icon-ban.svg` / `icon-check.svg` style). Use a simple two-chamber hourglass, 16×16 viewBox, strokes on `currentColor` so mask-based recolouring works.

### 2. Register the icon class

`css_library/components.css`, alongside the existing `.ui-icon-*` block:

```css
.ui-icon-hourglass {
  -webkit-mask-image: url("./icons/icon-hourglass.svg");
          mask-image: url("./icons/icon-hourglass.svg");
}
```

### 3. Add status-colour helpers (or reuse existing text-utility classes)

If `.text-success` / `.text-danger` / `.text-warning` utilities already exist in `layout.css`, reuse them on the icon span. If not, add three small helpers so the cell stays one element:

```css
.ui-icon.is-success { color: var(--color-success); }
.ui-icon.is-danger  { color: var(--color-danger); }
.ui-icon.is-warning { color: var(--color-warning); }
```

`.ui-icon` already uses `background-color: currentColor` in the mask pattern, so `color:` cascade does the recolour.

### 4. Rewrite the two render helpers in `UsersTab.php`

```php
$statusIcon = static function (string $s) use ($h): string {
    return match ($s) {
        "activated"      => "<span class=\"ui-icon ui-icon-check is-success\" aria-label=\"aktiv\" title=\"aktiv\"></span>",
        "invite-pending" => "<span class=\"ui-icon ui-icon-hourglass is-warning\" aria-label=\"Einladung offen\" title=\"Einladung offen\"></span>",
        "reset-pending"  => "<span class=\"ui-icon ui-icon-hourglass is-warning\" aria-label=\"Passwort-Reset offen\" title=\"Passwort-Reset offen\"></span>",
        default          => "<span class=\"ui-icon\" aria-label=\"$s\" title=\"$s\"></span>",
    };
};

$disabledIcon = static fn(int $d): string => $d
    ? "<span class=\"ui-icon ui-icon-close is-danger\" aria-label=\"deaktiviert\" title=\"deaktiviert\"></span>"
    : "<span class=\"ui-icon ui-icon-check is-success\" aria-label=\"aktiv\" title=\"aktiv\"></span>";
```

Call sites (lines 107, 109-111) use the new helpers instead of the badge markup.

### 5. Header cell alignment

Add `style="text-align:center"` or a utility class to the `<th>Status</th>` and `<th>Deaktiviert</th>` headers plus their `<td>`s so the icon is visually centred in the column. Body column widths can also shrink — the column no longer needs to fit "Passwort-Reset offen".

## Accessibility

- Icon carries both `aria-label` (for SR) and `title` (for mouse-hover tooltip). Per the audit in css_library TASK-4, this covers quick-win #9-style cases cleanly.
- Colour is not the only information carrier: the icon shape itself differs (check vs X vs hourglass), so red/green colour-blind users still get semantic meaning.
- No reliance on emoji fonts — monochrome SVG inherits `currentColor`, so dark-mode contrast follows the token system automatically.

## Not in this task

- Changing the Rechte (rights) column rendering.
- Adding a distinct icon for reset-pending vs invite-pending (hourglass serves both; can be a follow-up if UX feedback wants it).
- Applying the same icon-in-place-of-text pattern elsewhere (extraColumns in consumer apps, the log tab "nur Fehler" filter, etc.) — each stays a deliberate decision per view.
- Re-styling the badge component itself — still used elsewhere (log tab row counts, alert counts).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 New icon file css_library/icons/icon-hourglass.svg added, authored as a mask-compatible monochrome SVG
- [x] #2 components.css registers .ui-icon-hourglass using the existing mask-image pattern
- [x] #3 .ui-icon.is-success / .is-danger / .is-warning colour helpers exist (or equivalent utility classes are reused)
- [x] #4 UsersTab.php Status column renders: check (green) for activated, hourglass (amber) for invite-pending, hourglass (amber) for reset-pending
- [x] #5 UsersTab.php Deaktiviert column renders: close/X (red) when disabled, check (green) when active
- [x] #6 Each icon has matching aria-label AND title text in German for screen readers and mouse hover
- [x] #7 Header cells and body cells for Status and Deaktiviert are centre-aligned
- [ ] #8 Visual check in all consuming apps with admin access (Energie, wlmonitor, zeiterfassung, simplechat, suche): icons render correctly in light and dark modes
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced text badges in Status and Deaktiviert columns with mask-based UI icons.

**css_library/icons/icon-hourglass.svg** — new monochrome filled-path SVG (24×24 viewBox, same style as icon-check.svg / icon-close.svg)

**css_library/components.css** — added `.ui-icon-hourglass` mask registration + three semantic colour modifiers: `.ui-icon.is-success`, `.ui-icon.is-danger`, `.ui-icon.is-warning` (set `color:` so `background-color: currentColor` recolours the mask)

**chrome/src/Admin/UsersTab.php** — replaced `$statusBadge` closure with `$statusIcon` + new `$disabledIcon` arrow function; both `<th>` and both `<td>` cells get `style="text-align:center"`; each icon span carries matching `aria-label` and `title` in German
<!-- SECTION:FINAL_SUMMARY:END -->
