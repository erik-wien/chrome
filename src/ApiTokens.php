<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Erikr\Chrome\ApiTokens — "Tokens verwalten" dialog on the Profile page
 * (Erik, 2026-07-25: "Die API-Token Verwaltung ist jetzt direkt im Profil.
 * Das ist unschön. Schreib nur: 'Tokens verwalten (#)' und mach das analog
 * Kennwort ändern in einem eigenen Dialog." — supersedes the earlier inline
 * block from 2026-07-24). Tokens are account-wide (bound to `user_id` in the
 * shared auth DB, table `auth_api_tokens`) — a token created in one app is
 * valid for the same account in every app. That is intentional, not a bug
 * to fix here.
 *
 * Split out of Profile.php (already large) into its own class — a
 * self-contained, independently testable unit, following the AvatarCropModal
 * precedent: render() emits ONLY the `.app-modal-backdrop` dialog markup
 * (canonical catalog pattern, see css_library/components.css and
 * src/Admin/UserModals.php). The trigger button ("Tokens verwalten (N)",
 * styled/placed analogous to "Kennwort ändern") lives in Profile.php itself,
 * next to "Kennwort ändern" — see Profile::render()'s docblock. Opening/
 * closing goes through the suite's shared `openModal()`/`closeModal()`
 * (css_library/js/admin.js) — focus trap, Escape-to-close and
 * previously-focused-element restore are already solved there; loading that
 * script here reuses it rather than reimplementing a fourth modal-open/close
 * pair (see Profile::render()'s `adminJsPath`). admin.js degrades to a no-op
 * for markup it doesn't find (tabs, reset-password modal), so loading it on
 * a non-admin page is safe.
 *
 * This class is a pure renderer: it takes an already-fetched token list via
 * `tokens` (the app calls `auth_api_tokens_list($con, $uid)` itself, same
 * pattern as Status/Admin — no DB/auth-library calls from render() paths in
 * this repo). It never sees cleartext tokens; those only ever exist for one
 * response, produced by the app's own POST handler (see contract below) and
 * displayed once by the client-side JS.
 *
 * ── $cfg contract ───────────────────────────────────────────────────────
 *
 *   tokens      list<array{id:int,label:string,source:string,
 *                           created_at:string,last_used_at:?string,
 *                           expires_at:?string}>
 *                              Required. Output of auth_api_tokens_list().
 *   action      string        Required. POST target for token_create /
 *                              token_revoke (see contract below) — typically
 *                              the same page (self).
 *   csrfToken   string        csrf_token() — embedded in the create form and
 *                              read by the JS for the revoke fetch.
 *   cspNonce    string        $_cspNonce — applied to the inline <script>.
 *   jsPath      string        Path to the shared behaviour module. Default
 *                              `$base/css/shared/js/api-tokens.js`
 *                              (css_library/js/api-tokens.js). Loaded as
 *                              `<script type="module">` — its top-level code
 *                              self-initialises by reading `data-action`/
 *                              `data-csrf-token` off the `#apiTokensBlock`
 *                              element (now the dialog's `.app-modal-body`
 *                              itself — see below) once the module has
 *                              loaded (module scripts execute after HTML
 *                              parsing completes, like `defer` — no
 *                              dependency on script-tag order relative to a
 *                              plain inline caller, the trap that would exist
 *                              if this were wired the way avatar-cropper.js's
 *                              plain-script `initAvatarCropper({...})` call
 *                              is).
 *
 * ── POST contract the app's page (the `action`/`tokenAction` target) must
 *    implement — UNCHANGED from the previous inline block, see chrome
 *    commit c87acfa's docblock; repeated here verbatim so this class stays
 *    self-documenting ─────────────────────────────────────────────────────
 *
 * Both routes are fetch-based, JSON in and out, and require CSRF — on CSRF
 * failure the handler MUST still respond with JSON (never a redirect/HTML
 * error page: a redirect response reads as an opaque "HTTP 200" to fetch()
 * and swallows the actual cause).
 *
 * 1) Token anlegen — POST `action`:
 *      csrf_token = csrf_token()
 *      action     = "token_create"
 *      label      = user-entered label (may be empty)
 *    Success → HTTP 200, `Content-Type: application/json`:
 *      {"ok": true, "token": "<cleartext, shown ONCE>", "item": {
 *          "id": 1, "label": "…", "source": "web",
 *          "created_at": "2026-07-25 10:00:00", "last_used_at": null,
 *          "expires_at": null
 *      }}
 *    Failure → HTTP 4xx: {"ok": false, "error": "<cause>"}
 *    Typical handler body:
 *      if (!csrf_verify()) {
 *          header('Content-Type: application/json');
 *          http_response_code(400);
 *          echo json_encode(['ok' => false, 'error' => 'Ungültige Anfrage (CSRF).']);
 *          exit;
 *      }
 *      $label = trim((string) ($_POST['label'] ?? ''));
 *      $token = auth_api_token_issue($con, $uid, $label, 'web', null);
 *      $item  = auth_api_tokens_list($con, $uid)[0] ?? null; // just inserted, created_at DESC
 *      header('Content-Type: application/json');
 *      echo json_encode(['ok' => true, 'token' => $token, 'item' => $item]);
 *      exit;
 *    NEVER log $token (the cleartext) — only the label/id, if anything.
 *
 * 2) Token widerrufen — POST `action`:
 *      csrf_token = csrf_token()
 *      action     = "token_revoke"
 *      id         = token id (int)
 *    Success → HTTP 200: {"ok": true}
 *    Failure (bad/foreign id, CSRF) → HTTP 4xx: {"ok": false, "error": "<cause>"}
 *    Typical handler body:
 *      if (!csrf_verify()) { …same JSON-on-CSRF-failure as above… }
 *      $id = (int) ($_POST['id'] ?? 0);
 *      $deleted = auth_api_token_revoke($con, $uid, $id);
 *      header('Content-Type: application/json');
 *      if (!$deleted) {
 *          http_response_code(404);
 *          echo json_encode(['ok' => false, 'error' => 'Token nicht gefunden oder bereits widerrufen.']);
 *          exit;
 *      }
 *      echo json_encode(['ok' => true]);
 *      exit;
 *
 * No emojis (Rule §11), all dynamic values htmlspecialchars()-escaped, no
 * hardcoded colors (Rule §1/§9), no inline `style="…"` attributes — spacing
 * uses the canonical `.mt-2`/`.mt-3` utilities (layout.css). Buttons: "Token
 * anlegen" and "Widerrufen" are both data-changing/non-primary →
 * `.btn-outline-danger` (Rule §7.1), both real `<button>` elements; "Schließen"
 * is neutral (changes nothing) → bare `.btn`. The list markup
 * (`.list-group-item`, `.list-unstyled`, `.d-flex`, `.gap-2`, `.flex-column`,
 * `.text-muted`, `.fw-semibold`, `.align-items-center`,
 * `.justify-content-between`) uses only classes defined in css_library's own
 * components.css/layout.css (verified) — these are this design system's own
 * utility/component classes, not an import of an external framework.
 */
final class ApiTokens
{
    /**
     * Number of valid token rows — used by Profile::render() for the
     * "Tokens verwalten (N)" trigger button label (shown even at N=0).
     *
     * @param array<mixed> $tokens
     */
    public static function count(array $tokens): int
    {
        return count(array_values(array_filter($tokens, 'is_array')));
    }

    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg): void
    {
        $tokens    = array_values(array_filter((array) ($cfg['tokens'] ?? []), 'is_array'));
        $action    = (string) ($cfg['action'] ?? '');
        $csrf      = (string) ($cfg['csrfToken'] ?? '');
        $nonce     = (string) ($cfg['cspNonce'] ?? '');
        $jsPath    = (string) ($cfg['jsPath'] ?? '');

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

        echo '<div class="app-modal-backdrop" id="apiTokensModal" aria-hidden="true" role="dialog" '
           . 'aria-modal="true" aria-labelledby="apiTokensModal-titel" hidden>';
        echo   '<div class="app-modal-dialog app-modal-sm">';
        echo     '<div class="app-modal-header"><div class="app-modal-header-row">';
        echo       '<h2 class="app-modal-title" id="apiTokensModal-titel">Tokens verwalten</h2>';
        echo       '<button type="button" class="app-modal-close btn icon-btn" data-modal-close '
                 . 'aria-label="Schließen"><span aria-hidden="true">&times;</span></button>';
        echo     '</div></div>';

        // #apiTokensBlock IS the dialog body (not a separate wrapper) — the
        // JS module (api-tokens.js) only needs an element carrying
        // data-action/data-csrf-token that it can call querySelector() on;
        // it does not care about ancestor classes.
        echo     '<div class="app-modal-body" id="apiTokensBlock" data-action="' . $e($action) . '" '
               . 'data-csrf-token="' . $e($csrf) . '">';

        echo       '<p class="text-muted">App-Tokens erlauben Geräten oder Skripten den Zugriff auf Ihr Konto '
                 . 'ohne Kennwort. Beim Erstellen wird das Token einmalig angezeigt — sichern Sie es sofort.</p>';

        echo       '<div id="apiTokensError" class="app-alert app-alert-danger" role="alert" hidden></div>';

        echo       '<div id="apiTokenReveal" class="app-alert app-alert-info" hidden aria-live="polite">';
        echo         '<p><strong>Token jetzt sichern</strong> — es wird nie wieder angezeigt.</p>';
        echo         '<div class="form-group"><input type="text" id="apiTokenRevealField" class="form-control" readonly></div>';
        echo         '<div class="mt-2">';
        echo           '<button type="button" class="btn btn-sm" id="apiTokenRevealCopy">Kopieren</button> ';
        echo           '<button type="button" class="btn btn-sm" id="apiTokenRevealDone">Fertig</button>';
        echo         '</div>';
        echo       '</div>';

        $listHidden  = $tokens === [] ? ' hidden' : '';
        $emptyHidden = $tokens === [] ? '' : ' hidden';
        echo       '<p id="apiTokensEmpty" class="text-muted"' . $emptyHidden . '>Noch keine API-Token erstellt.</p>';
        echo       '<ul id="apiTokensList" class="list-unstyled d-flex flex-column gap-2"' . $listHidden . '>';
        foreach ($tokens as $t) {
            echo self::renderRow($t);
        }
        echo       '</ul>';

        echo       '<form id="apiTokenCreateForm" class="mt-3">';
        echo         '<div class="form-group"><label for="apiTokenLabel">Bezeichnung</label>';
        echo         '<input type="text" id="apiTokenLabel" name="label" class="form-control" maxlength="100" '
                   . 'placeholder="z. B. Gerätename"></div>';
        echo         '<button type="submit" class="btn btn-outline-danger">Token anlegen</button>';
        echo       '</form>';

        echo     '</div>'; // .app-modal-body#apiTokensBlock

        echo     '<div class="app-modal-footer">';
        echo       '<button type="button" class="btn" data-modal-close>Schließen</button>';
        echo     '</div>';

        echo   '</div>'; // .app-modal-dialog
        echo '</div>'; // .app-modal-backdrop#apiTokensModal

        echo '<script type="module" src="' . $e($jsPath) . '"' . $nonceAttr . '></script>';
    }

    /**
     * Renders a single `<li>` token row. Shared between the server-rendered
     * initial list and (as an HTML string embedded in a JSON response, if an
     * app ever wants it) client-side re-rendering — kept here so the markup
     * has exactly one source of truth. The JS module builds the equivalent
     * DOM itself for newly created tokens (no round-trip needed for that).
     *
     * @param array{id:int,label:string,source:string,created_at:string,
     *              last_used_at:?string,expires_at:?string} $t
     */
    private static function renderRow(array $t): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $id     = (int) ($t['id'] ?? 0);
        $label  = (string) ($t['label'] ?? '');
        $label  = $label !== '' ? $label : '(ohne Bezeichnung)';
        $source = (string) ($t['source'] ?? '');
        $quelle = match ($source) {
            'web'         => 'Web',
            'credentials' => 'Zugangsdaten',
            default       => $source !== '' ? $source : 'Unbekannt',
        };
        $created  = self::formatDate((string) ($t['created_at'] ?? ''));
        $lastUsed = $t['last_used_at'] ?? null;
        $meta = $quelle . ' · erstellt ' . $created
              . ' · zuletzt genutzt ' . ($lastUsed !== null ? self::formatDate((string) $lastUsed) : 'nie');

        $html  = '<li class="list-group-item d-flex align-items-center justify-content-between gap-2" '
               . 'data-token-id="' . $id . '" data-token-label="' . $e($label) . '">';
        $html .= '<div><div class="fw-semibold">' . $e($label) . '</div>';
        $html .= '<div class="text-muted">' . $e($meta) . '</div></div>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-danger" data-token-revoke '
               . 'aria-label="Token „' . $e($label) . '“ widerrufen">'
               . '<span class="ui-icon ui-icon-delete" aria-hidden="true"></span> Widerrufen</button>';
        $html .= '</li>';

        return $html;
    }

    private static function formatDate(string $dt): string
    {
        if ($dt === '') {
            return '';
        }
        $ts = strtotime($dt);
        return $ts !== false ? date('d.m.Y', $ts) : $dt;
    }
}
