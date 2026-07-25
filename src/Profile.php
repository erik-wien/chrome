<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Profile page content — Erik-approved mockup (2026-07-25). Replaces the old
 * "Benutzereinstellungen" dropdown group (Profilbild/E-Mail), the top-level
 * "Sicherheit" link and "Anwendung"/appPrefs, which used to live directly in
 * Header's user dropdown (see Header::render()'s deprecated options).
 *
 * Renders CONTENT only — no page shell (`<!DOCTYPE>`/`<head>`/Header/Footer).
 * render() emits its OWN `<div class="pref-section profile-page">` wrapper
 * (520px cap per Rule §16) — the caller must NOT wrap the render() call in
 * another `.pref-section` div. Doing so nests two 520px-cap containers
 * (harmless visually, but a Cap-auf-Cap anti-pattern §16 explicitly avoids).
 * Put render() directly inside `<main>` (or an app's own header/back-link
 * wrapper, e.g. an admin-page shell) alongside any alerts the app wants to
 * show above it — those don't need a `.pref-section` of their own either;
 * render()'s wrapper already covers the whole block.
 *
 * The page renders its own `<h1>` (see `title` below, defaults to "Profil")
 * as the first element inside the `.pref-section` wrapper — apps must NOT
 * set a second heading of their own above render().
 *
 * The app wires its own `profil.php` (typically the `profilHref` target
 * configured on Header::render()) around it:
 *
 *   require __DIR__ . '/../inc/initialize.php';
 *   auth_require();
 *   // … handle the POSTs described below, then …
 *   \Erikr\Chrome\Profile::render([
 *       'avatarSrc'          => $base . '/avatar.php',
 *       'username'           => $_SESSION['username'],
 *       'email'              => $currentEmail,
 *       'emailEditAction'    => 'profil.php',
 *       'avatarChangeAction' => 'profil.php',
 *       'passwordHref'       => $base . '/password.php',
 *       'csrfToken'          => csrf_token(),
 *       'cspNonce'           => $_cspNonce,
 *       'emailError'         => $emailError ?? null,
 *       'tokens'             => auth_api_tokens_list($con, $uid),
 *       'tokenAction'        => 'profil.php',
 *       'appSections'        => [
 *           ['label' => 'Benachrichtigungen', 'href' => 'notifications.php'],
 *           ['html'  => '<form method="post" action="profil.php">…</form>'],
 *       ],
 *   ]);
 *
 * Layout (top to bottom): `<h1>` heading, 128px round avatar + "Profilbild
 * ändern" button (+ optional "Profilbild entfernen" button, see
 * `avatarClearAction`), "Benutzername" (display-only, no edit — deferred per
 * Erik), "E-Mail" (value + pencil edit button), "Kennwort ändern" button,
 * optional "API-Token" section (see `tokens`/`tokenAction` — Erikr\Chrome\
 * ApiTokens, rendered only when `tokenAction` is set), divider, appSections
 * listed one per row (never as side-by-side pills).
 *
 * ── $cfg contract ─────────────────────────────────────────────────────────
 *
 *   title                string|null   `<h1>` text, first element inside the
 *                                       `.pref-section` wrapper. Defaults to
 *                                       "Profil". Pass `null` to suppress the
 *                                       heading entirely (only for apps whose
 *                                       own page shell already renders one —
 *                                       apps should NOT do this by default;
 *                                       see class docblock above).
 *   avatarSrc           string        URL of the current avatar image.
 *   username             string        Display only — no edit control (Erik:
 *                                       deferred; do not add a pencil here).
 *   email                string        Display only; edited via the pencil.
 *   emailEditAction      string|null   POST target for the inline "change
 *                                       e-mail" form (see contract below).
 *                                       When set, the pencil toggles the
 *                                       inline form open/closed.
 *   emailEditHref        string|null   Alternative to emailEditAction: when
 *                                       emailEditAction is NOT set and this
 *                                       is, the pencil renders as a plain
 *                                       link to a separate app page instead
 *                                       of an inline form. emailEditAction
 *                                       wins if both are set. Neither set →
 *                                       no pencil is rendered.
 *   emailError           string|null   Validation/error message re-shown
 *                                       above the inline e-mail form after a
 *                                       failed POST (keeps the form open).
 *   avatarChangeAction   string        POST target for the avatar upload
 *                                       (see contract below). Required for
 *                                       the crop-and-upload flow to work.
 *   avatarClearAction    string|null   POST target for "Profilbild
 *                                       entfernen" (see contract below).
 *                                       When set, a second, dezent
 *                                       `.btn-outline-danger` button is
 *                                       rendered next to "Profilbild
 *                                       ändern" (Rule §7.1 — removing/
 *                                       data-changing, non-primary action).
 *                                       Not set → no such button, no
 *                                       behavior change (back-compat).
 *   passwordHref         string        "Kennwort ändern" button target
 *                                       (plain navigation, e.g. password.php
 *                                       — no POST contract here).
 *   csrfToken            string        csrf_token() — embedded in both the
 *                                       inline e-mail form and the avatar
 *                                       upload JS.
 *   cspNonce             string        $_cspNonce — applied to every inline
 *                                       <script> this method emits.
 *   base                 string        Optional. Used only to build default
 *                                       paths for the Cropper.js assets
 *                                       below; pass the explicit *Path keys
 *                                       instead if your app's layout differs.
 *   cropperCssPath        string        Default `$base/css/shared/js/vendor/cropperjs/cropper.min.css`.
 *   cropperJsPath         string        Default `$base/css/shared/js/vendor/cropperjs/cropper.min.js`.
 *   avatarCropperJsPath   string        Default `$base/css/shared/js/avatar-cropper.js`.
 *   dialogJsPath          string        Only loaded when `avatarClearAction`
 *                                       is set (for the confirm dialog on
 *                                       removal). Default
 *                                       `$base/css/shared/js/dialog.js`
 *                                       (css_library's `confirmDialog()`/
 *                                       `alertDialog()` module — see
 *                                       css_library/js/dialog.js). Loading it
 *                                       twice on a page that already includes
 *                                       it elsewhere is harmless: the browser
 *                                       dedupes module scripts by URL.
 *   tokens               list<array>   Output of `auth_api_tokens_list()`.
 *                                       Only rendered when `tokenAction` is
 *                                       also set (see below); defaults to
 *                                       `[]` (empty-state text shown).
 *   tokenAction          string|null   POST target for the "API-Token"
 *                                       section — Erikr\Chrome\ApiTokens
 *                                       (token_create/token_revoke, see its
 *                                       own docblock for the exact contract).
 *                                       Not set (default) → the whole section
 *                                       is omitted, no behavior change
 *                                       (back-compat).
 *   apiTokensJsPath       string        Only used when `tokenAction` is set.
 *                                       Default
 *                                       `$base/css/shared/js/api-tokens.js`.
 *   appSections          list<array>   Each entry is either
 *                                       `['label' => …, 'href' => …]` (a
 *                                       plain link row) or `['html' => …]`
 *                                       (raw HTML, e.g. a small form) —
 *                                       trusted app markup, not escaped.
 *                                       Rendered one per row, full width
 *                                       (never as side-by-side pills — Erik
 *                                       correction).
 *
 * ── POST contract apps must implement on the page that calls render() ─────
 *
 * 1) Avatar change — fetch-based (see css_library/js/avatar-cropper.js),
 *    triggered client-side once the user picks + crops a file. POSTs
 *    multipart/form-data to `avatarChangeAction`:
 *      csrf_token  = csrf_token()
 *      action      = "upload_avatar"
 *      avatar      = <Blob>, JPEG, filename "avatar.jpg"
 *    The handler MUST respond with `Content-Type: application/json`:
 *      success → `{"ok": true}`
 *      failure → HTTP 400 + `{"ok": false, "error": "<message>"}`
 *    Typical handler body:
 *      $res = \Erikr\Chrome\AvatarUpload::handle($con, $uid, $_FILES['avatar'] ?? null);
 *      header('Content-Type: application/json');
 *      if ($res['ok']) { echo json_encode(['ok' => true]); exit; }
 *      http_response_code(400);
 *      echo json_encode(['ok' => false, 'error' => '…']); exit;
 *
 * 2) E-mail change — plain browser POST (full page reload), triggered by
 *    the inline form (or the app's own page when using emailEditHref).
 *    POSTs to `emailEditAction`:
 *      csrf_token      = csrf_token()
 *      action          = "change_email"
 *      email           = new address
 *      email_password  = current password, for confirmation
 *    The handler verifies csrf_verify() + the password, then calls
 *    auth_email_confirmation_issue() + mail_send_email_change_confirmation()
 *    (see suche/web/preferences.php, last.fm/web/preferences.php) and either
 *    redirects with a flash alert on success, or re-renders the profile page
 *    passing the failure back via the `emailError` cfg key.
 *
 * 3) Avatar removal — fetch-based, same JSON contract as (1), only rendered
 *    when `avatarClearAction` is set. Triggered client-side after the user
 *    confirms via `confirmDialog()` (css_library/js/dialog.js, loaded by
 *    this method — see `dialogJsPath`); no native `confirm()`. POSTs
 *    multipart/form-data to `avatarClearAction`:
 *      csrf_token  = csrf_token()
 *      action      = "clear_avatar"
 *    The handler MUST respond with `Content-Type: application/json`:
 *      success → `{"ok": true}`
 *      failure → HTTP 400 + `{"ok": false, "error": "<message>"}`
 *    Typical handler body:
 *      \Erikr\Chrome\AvatarUpload::clear($con, $uid);
 *      header('Content-Type: application/json');
 *      echo json_encode(['ok' => true]); exit;
 *
 * 4) API tokens — fetch-based, only when `tokenAction` is set. Full contract
 *    (both `action=token_create` and `action=token_revoke`, CSRF-on-JSON
 *    requirement, response shapes) documented on Erikr\Chrome\ApiTokens —
 *    see that class's docblock. Same `tokenAction` target handles both.
 *
 * Uses the existing AvatarCropModal (src/AvatarCropModal.php) and, when
 * `tokenAction` is set, Erikr\Chrome\ApiTokens (src/ApiTokens.php) — no new
 * crop UI, token markup lives in its own class (see ApiTokens.php's
 * docblock for why it isn't inlined here). No emojis (Rule §11) — the edit
 * affordance is `.ui-icon-edit`. All dynamic values are
 * htmlspecialchars()-escaped except `appSections[]['html']`, which is
 * trusted raw markup like Header's `extraItems`.
 */
final class Profile
{
    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg): void
    {
        $title               = array_key_exists('title', $cfg) ? $cfg['title'] : 'Profil';
        $avatarSrc          = (string) ($cfg['avatarSrc'] ?? '');
        $username            = (string) ($cfg['username']  ?? '');
        $email               = (string) ($cfg['email']     ?? '');
        $emailEditAction     = array_key_exists('emailEditAction', $cfg) ? $cfg['emailEditAction'] : null;
        $emailEditHref       = array_key_exists('emailEditHref', $cfg) ? $cfg['emailEditHref'] : null;
        $emailError          = $cfg['emailError'] ?? null;
        $avatarChangeAction  = (string) ($cfg['avatarChangeAction'] ?? '');
        $avatarClearAction   = array_key_exists('avatarClearAction', $cfg) ? $cfg['avatarClearAction'] : null;
        $passwordHref        = (string) ($cfg['passwordHref'] ?? '#');
        $csrf                = (string) ($cfg['csrfToken'] ?? '');
        $nonce               = (string) ($cfg['cspNonce']  ?? '');
        $tokens              = (array)  ($cfg['tokens'] ?? []);
        $tokenAction         = array_key_exists('tokenAction', $cfg) ? $cfg['tokenAction'] : null;
        $appSections         = (array)  ($cfg['appSections'] ?? []);

        $base                = rtrim((string) ($cfg['base'] ?? ''), '/');
        $cropperCssPath      = (string) ($cfg['cropperCssPath']      ?? ($base . '/css/shared/js/vendor/cropperjs/cropper.min.css'));
        $cropperJsPath       = (string) ($cfg['cropperJsPath']       ?? ($base . '/css/shared/js/vendor/cropperjs/cropper.min.js'));
        $avatarCropperJsPath = (string) ($cfg['avatarCropperJsPath'] ?? ($base . '/css/shared/js/avatar-cropper.js'));
        $dialogJsPath        = (string) ($cfg['dialogJsPath']        ?? ($base . '/css/shared/js/dialog.js'));
        $apiTokensJsPath     = (string) ($cfg['apiTokensJsPath']     ?? ($base . '/css/shared/js/api-tokens.js'));

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

        echo '<style' . $nonceAttr . '>';
        // appSections stapeln sich als volle-Breite-Schaltflächen. Das Aussehen
        // liefert .btn (components.css); hier nur Breite/Ausrichtung, damit die
        // Zeilen untereinander stehen (Erik: nicht als Pillen nebeneinander).
        echo '.profile-app-sections{display:flex;flex-direction:column;gap:.5rem}';
        echo '.profile-app-section-item{width:100%;justify-content:flex-start;text-align:left}';
        echo '.profile-divider{border:0;border-top:1px solid var(--color-border);margin:1rem 0}';
        // Component-specific sizing with no direct utility-class equivalent
        // (border-radius:50% + object-fit combo) — everything else below uses
        // the canonical spacing/alignment utilities from layout.css instead
        // of inline styles (.text-center, .mb-4, .mt-3/.mb-3).
        echo '.profile-avatar-img{width:128px;height:128px;border-radius:50%;'
           . 'object-fit:cover;display:block;margin:0 auto .75rem}';
        echo '</style>';

        echo '<div class="pref-section profile-page">';

        if ($title !== null) {
            echo '<h1>' . $e((string) $title) . '</h1>';
        }

        // ── Avatar ────────────────────────────────────────────────────────
        echo '<div class="profile-avatar-block text-center mb-4">';
        echo '<img src="' . $e($avatarSrc) . '" alt="" width="128" height="128" class="profile-avatar-img">';
        echo '<input type="file" id="profileAvatarFile" class="visually-hidden" '
           . 'accept="image/jpeg,image/png,image/gif,image/webp">';
        // <label class="btn"> as the file-picker trigger: no separate
        // cursor:pointer needed here — `.btn` in components.css already sets
        // `cursor: pointer` on the class itself (applies to any element,
        // label included), unlike the `style="cursor:pointer"` duplicates
        // seen in some apps' own admin pages (predating that base rule).
        echo '<label class="btn" for="profileAvatarFile">Profilbild ändern</label>';
        if ($avatarClearAction !== null) {
            echo ' <button type="button" class="btn btn-outline-danger" id="profileAvatarClear">Profilbild entfernen</button>';
        }
        echo '</div>';

        echo '<link rel="stylesheet" href="' . $e($cropperCssPath) . '">';
        AvatarCropModal::render();
        echo '<script src="' . $e($cropperJsPath) . '"' . $nonceAttr . '></script>';
        echo '<script src="' . $e($avatarCropperJsPath) . '"' . $nonceAttr . '></script>';
        echo '<script' . $nonceAttr . '>';
        echo '(function(){initAvatarCropper({';
        echo 'fileInputId:' . json_encode('profileAvatarFile') . ',';
        echo 'modalId:' . json_encode('avatarCropModal') . ',';
        echo 'imageId:' . json_encode('avatarCropImage') . ',';
        echo 'confirmId:' . json_encode('avatarCropConfirm') . ',';
        echo 'cancelId:' . json_encode('avatarCropCancel') . ',';
        echo 'formAction:' . json_encode($avatarChangeAction) . ',';
        echo 'csrfToken:' . json_encode($csrf);
        echo '});})();';
        echo '</script>';

        if ($avatarClearAction !== null) {
            echo '<script type="module" src="' . $e($dialogJsPath) . '"' . $nonceAttr . '></script>';
            echo '<script' . $nonceAttr . '>';
            echo '(function(){';
            echo 'var btn=document.getElementById("profileAvatarClear");';
            echo 'if(!btn)return;';
            echo 'var clearAction=' . json_encode($avatarClearAction) . ';';
            echo 'var csrfToken=' . json_encode($csrf) . ';';
            echo 'function doClear(){';
            echo 'btn.disabled=true;';
            echo 'var fd=new FormData();';
            echo 'fd.append("csrf_token",csrfToken);';
            echo 'fd.append("action","clear_avatar");';
            echo 'fetch(clearAction,{method:"POST",body:fd,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})';
            echo '.then(function(res){return res.json().catch(function(){throw new Error("HTTP "+res.status);}).then(function(data){';
            echo 'if(!res.ok||!data.ok){throw new Error(data.error||("HTTP "+res.status));}return data;});})';
            echo '.then(function(){window.location.reload();})';
            echo '.catch(function(err){';
            echo 'var msg="Entfernen fehlgeschlagen: "+err.message;';
            echo 'if(window.alertDialog){window.alertDialog(msg);}else{alert(msg);}';
            echo 'btn.disabled=false;';
            echo '});';
            echo '}';
            echo 'btn.addEventListener("click",function(){';
            echo 'if(window.confirmDialog){';
            echo 'window.confirmDialog("Profilbild wirklich entfernen?",{gefahr:"secondary",okLabel:"Entfernen"}).then(function(ok){if(ok)doClear();});';
            echo '}else{';
            echo 'var msg="Bestätigungsdialog konnte nicht geladen werden. Bitte Seite neu laden.";';
            echo 'if(window.alertDialog){window.alertDialog(msg);}else{alert(msg);}';
            echo '}';
            echo '});';
            echo '})();';
            echo '</script>';
        }

        // ── Benutzername / E-Mail ────────────────────────────────────────
        echo '<dl class="app-kv">';
        echo '<dt>Benutzername</dt><dd>' . $e($username) . '</dd>';
        echo '<dt>E-Mail</dt><dd>' . $e($email) . ' ';
        if ($emailEditAction !== null) {
            echo '<button type="button" class="btn btn-sm btn-icon" id="profileEmailEditToggle" '
               . 'aria-expanded="false" aria-controls="profileEmailForm" aria-label="E-Mail ändern">'
               . '<span class="ui-icon ui-icon-edit" aria-hidden="true"></span></button>';
        } elseif ($emailEditHref !== null) {
            echo '<a href="' . $e((string) $emailEditHref) . '" class="btn btn-sm btn-icon" aria-label="E-Mail ändern">'
               . '<span class="ui-icon ui-icon-edit" aria-hidden="true"></span></a>';
        }
        echo '</dd>';
        echo '</dl>';

        // ── Inline e-mail change form (only in emailEditAction mode) ────────
        if ($emailEditAction !== null) {
            $panelHidden = $emailError === null ? ' hidden' : '';
            echo '<div id="profileEmailForm"' . $panelHidden . '>';
            echo '<form method="post" action="' . $e((string) $emailEditAction) . '">';
            if ($csrf !== '') {
                echo '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '">';
            }
            echo '<input type="hidden" name="action" value="change_email">';
            if ($emailError !== null) {
                echo '<div class="app-alert app-alert-danger" role="alert">' . $e((string) $emailError) . '</div>';
            }
            echo '<div class="form-group"><label for="profileNewEmail">Neue E-Mail-Adresse</label>';
            echo '<input type="email" id="profileNewEmail" name="email" class="form-control" required></div>';
            echo '<div class="form-group"><label for="profileEmailPassword">Kennwort zur Bestätigung</label>';
            echo '<input type="password" id="profileEmailPassword" name="email_password" '
               . 'class="form-control" autocomplete="current-password" required></div>';
            echo '<button type="submit" class="btn btn-outline-danger">Bestätigungslink senden</button>';
            echo '</form>';
            echo '</div>';

            echo '<script' . $nonceAttr . '>';
            echo '(function(){';
            echo 'var btn=document.getElementById("profileEmailEditToggle");';
            echo 'var panel=document.getElementById("profileEmailForm");';
            echo 'if(!btn||!panel)return;';
            echo 'btn.addEventListener("click",function(){';
            echo 'panel.hidden=!panel.hidden;';
            echo 'btn.setAttribute("aria-expanded",panel.hidden?"false":"true");';
            echo '});';
            echo '})();';
            echo '</script>';
        }

        // ── Kennwort ändern ───────────────────────────────────────────────
        echo '<div class="mt-3 mb-3">';
        echo '<a href="' . $e($passwordHref) . '" class="btn">Kennwort ändern</a>';
        echo '</div>';

        // ── API-Token (only when tokenAction is set — back-compat) ──────────
        if ($tokenAction !== null) {
            echo '<script type="module" src="' . $e($dialogJsPath) . '"' . $nonceAttr . '></script>';
            ApiTokens::render([
                'tokens'    => $tokens,
                'action'    => (string) $tokenAction,
                'csrfToken' => $csrf,
                'cspNonce'  => $nonce,
                'jsPath'    => $apiTokensJsPath,
            ]);
        }

        // ── App-spezifische Abschnitte ────────────────────────────────────
        if (!empty($appSections)) {
            echo '<hr class="profile-divider">';
            echo '<div class="profile-app-sections">';
            foreach ($appSections as $section) {
                if (!is_array($section)) {
                    continue;
                }
                if (isset($section['html'])) {
                    echo '<div class="profile-app-section-item">' . (string) $section['html'] . '</div>';
                } else {
                    // Kanonische Schaltfläche statt Link-im-Kasten: ein nackter
                    // <a> im gefüllten Container sah aus wie ein deaktiviertes
                    // Eingabefeld (Erik, 2026-07-25). Volle Breite, gestapelt.
                    $label = (string) ($section['label'] ?? '');
                    $href  = (string) ($section['href']  ?? '#');
                    echo '<a class="btn profile-app-section-item" href="' . $e($href) . '">' . $e($label) . '</a>';
                }
            }
            echo '</div>';
        }

        echo '</div>'; // .profile-page
    }
}
