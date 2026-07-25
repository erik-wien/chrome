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
 *       'appSections'        => [
 *           ['label' => 'Benachrichtigungen', 'href' => 'notifications.php'],
 *           ['html'  => '<form method="post" action="profil.php">…</form>'],
 *       ],
 *   ]);
 *
 * Layout (top to bottom): 128px round avatar + "Profilbild ändern" button,
 * "Benutzername" (display-only, no edit — deferred per Erik), "E-Mail" (value
 * + pencil edit button), "Kennwort ändern" button, divider, appSections
 * listed one per row (never as side-by-side pills).
 *
 * ── $cfg contract ─────────────────────────────────────────────────────────
 *
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
 * Uses the existing AvatarCropModal (src/AvatarCropModal.php) — no new crop
 * UI. No emojis (Rule §11) — the edit affordance is `.ui-icon-edit`. All
 * dynamic values are htmlspecialchars()-escaped except `appSections[]['html']`,
 * which is trusted raw markup like Header's `extraItems`.
 */
final class Profile
{
    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg): void
    {
        $avatarSrc          = (string) ($cfg['avatarSrc'] ?? '');
        $username            = (string) ($cfg['username']  ?? '');
        $email               = (string) ($cfg['email']     ?? '');
        $emailEditAction     = array_key_exists('emailEditAction', $cfg) ? $cfg['emailEditAction'] : null;
        $emailEditHref       = array_key_exists('emailEditHref', $cfg) ? $cfg['emailEditHref'] : null;
        $emailError          = $cfg['emailError'] ?? null;
        $avatarChangeAction  = (string) ($cfg['avatarChangeAction'] ?? '');
        $passwordHref        = (string) ($cfg['passwordHref'] ?? '#');
        $csrf                = (string) ($cfg['csrfToken'] ?? '');
        $nonce               = (string) ($cfg['cspNonce']  ?? '');
        $appSections         = (array)  ($cfg['appSections'] ?? []);

        $base                = rtrim((string) ($cfg['base'] ?? ''), '/');
        $cropperCssPath      = (string) ($cfg['cropperCssPath']      ?? ($base . '/css/shared/js/vendor/cropperjs/cropper.min.css'));
        $cropperJsPath       = (string) ($cfg['cropperJsPath']       ?? ($base . '/css/shared/js/vendor/cropperjs/cropper.min.js'));
        $avatarCropperJsPath = (string) ($cfg['avatarCropperJsPath'] ?? ($base . '/css/shared/js/avatar-cropper.js'));

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

        echo '<div class="container-sm profile-page">';

        // ── Avatar ────────────────────────────────────────────────────────
        echo '<div class="profile-avatar-block" style="text-align:center;margin-bottom:1.5rem">';
        echo '<img src="' . $e($avatarSrc) . '" alt="" width="128" height="128" '
           . 'style="width:128px;height:128px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 0.75rem">';
        echo '<input type="file" id="profileAvatarFile" class="visually-hidden" '
           . 'accept="image/jpeg,image/png,image/gif,image/webp">';
        echo '<label class="btn" for="profileAvatarFile">Profilbild ändern</label>';
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
        echo '<div style="margin:1rem 0">';
        echo '<a href="' . $e($passwordHref) . '" class="btn">Kennwort ändern</a>';
        echo '</div>';

        // ── App-spezifische Abschnitte ────────────────────────────────────
        if (!empty($appSections)) {
            echo '<hr class="dropdown-divider">';
            echo '<div class="profile-app-sections">';
            foreach ($appSections as $section) {
                if (!is_array($section)) {
                    continue;
                }
                if (isset($section['html'])) {
                    echo '<div class="list-group-item">' . (string) $section['html'] . '</div>';
                } else {
                    $label = (string) ($section['label'] ?? '');
                    $href  = (string) ($section['href']  ?? '#');
                    echo '<div class="list-group-item"><a href="' . $e($href) . '">' . $e($label) . '</a></div>';
                }
            }
            echo '</div>';
        }

        echo '</div>'; // .profile-page
    }
}
