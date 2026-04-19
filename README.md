# erikr/chrome

Shared PHP library for the ~/Git app ecosystem's UI shell: fixed header, fixed footer, avatar endpoint, and the canonical admin screen (users tab + log tab + create/edit modals + AJAX dispatch router).

Every consumer app (wlmonitor, Energie, zeit, simplechat, suche) renders the same chrome by calling these classes — no per-app duplication of header markup, admin boilerplate, or avatar processing.

## Features

- **`Header`** — fixed top bar (Rule §12): brand · optional AppMenu · user dropdown (Einstellungen → Passwort & 2FA → Admin → Hilfe → Theme → Abmelden). CSP-nonce aware.
- **`Footer`** — fixed bottom bar (Rule §13): Impressum · © owner · version string.
- **`Avatar`** — public endpoint that serves `auth_accounts.img_blob`. Falls back to a neutral SVG silhouette on miss. Supports ETag / 304.
- **`AvatarUpload`** — GD-based center-crop to 205×205 JPEG, canonical MIME, persists via `auth_avatar_store()`.
- **`Admin\Dispatch`** — JSON API router. Every `?action=admin_*` call is admin-guarded, POST, CSRF-verified, returns `{ok, error?, …}`.
- **`Admin\Users`** — extended data access (activation state, last login + IP, invalid-login count, TOTP enrolment, reset-pending) over `auth_accounts` + `auth_invite_tokens` + `auth_log`.
- **`Admin\UsersTab`** — canonical §15 user-administration table + per-row actions. Supports `extraColumns` for app-specific fields (e.g. wlmonitor "Abfahrten").
- **`Admin\LogTab`** — canonical §15 log tab (AJAX shell; rows populated by `admin.js` against `admin_log_list`).
- **`Admin\UserModals`** — create + edit modals with `extraFields` for app-specific preferences.
- **`Admin\LogData`** — paginated / filtered reads of `auth_log`.

## Installation

Composer path repository pointing at this directory:

```json
{
    "repositories": [
        {"type": "path", "url": "../chrome"},
        {"type": "path", "url": "../auth"}
    ],
    "require": {
        "erikr/chrome": "*",
        "erikr/auth": "*"
    }
}
```

```bash
composer install
```

## Required consumer setup

```php
require 'vendor/autoload.php';

define('AUTH_DB_PREFIX', 'auth.');      // from erikr/auth
define('RATE_LIMIT_FILE', __DIR__ . '/data/ratelimit.json');
define('APP_VERSION', '1.2');
define('APP_BUILD',   '34');
define('APP_ENV',     'prod');                 // optional

auth_bootstrap();   // from erikr/auth — emits headers, starts session
// $con must be an open mysqli to the app DB
```

## Basic usage

```php
use Erikr\Chrome\Header;
use Erikr\Chrome\Footer;

Header::render([
    'appName'   => 'wlmonitor',
    'base'      => $base,
    'cspNonce'  => $_cspNonce,
    'csrfToken' => csrf_token(),
    'pageType'  => 'dashboard',
    'appMenu'   => [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'type' => 'dashboard'],
        ['href' => 'favorites.php', 'label' => 'Favoriten', 'type' => 'favorites'],
    ],
]);

// …page content…

Footer::render(['base' => $base]);
```

### Avatar endpoint

Each app's `web/avatar.php` becomes three lines:

```php
require_once __DIR__ . '/../inc/initialize.php';
\Erikr\Chrome\Avatar::serve($con);
```

### Admin screen

```php
// web/admin.php
require_once __DIR__ . '/../inc/initialize.php';
auth_require(); admin_require();

// Render three-tab shell (App-Parameter / Users / Log) — UsersTab +
// LogTab + UserModals cover the canonical admin layout.

\Erikr\Chrome\Admin\UsersTab::render([...]);
\Erikr\Chrome\Admin\LogTab::render();
\Erikr\Chrome\Admin\UserModals::render([...]);
```

```php
// web/api.php — dispatch admin_* actions
if (str_starts_with($action, 'admin_')) {
    \Erikr\Chrome\Admin\Dispatch::handle($con, $action, [
        'baseUrl' => $baseUrl,
        'selfId'  => (int) ($_SESSION['id'] ?? 0),
    ]);
    exit;
}
```

## Dependencies

- **PHP ≥ 8.2** with GD (for `AvatarUpload`)
- **erikr/auth** — session, CSRF, `admin_*` helpers, `appendLog()`, `auth_avatar_store()` / `_clear()`
- **Shared CSS** — `~/Git/css_library` symlinked into the app's `web/css/shared/`. Chrome emits class names (`.app-header`, `.user-menu`, `.card-header-split`, etc.) that the CSS library provides.

## Rules it implements

The chrome library is the canonical implementation of the shared UI design rules published at `~/Git/css_library/docs/design-rules.md`:

- **§12** — fixed header structure (brand / AppMenu / user dropdown with fixed item order)
- **§13** — fixed footer structure (Impressum / © / version)
- **§15** — admin screen three-tab layout, modal-based CRUD, AJAX dispatch
- **§15.1** — JSON API shape (POST + CSRF, `{ok, error?, …}`)
- **§15.2** — create / edit via modals, never inline forms

Apps that render chrome get compliance with those rules for free.
