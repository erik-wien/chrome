<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test for Erikr\Chrome\Profile::render() — same style as
 * tests/header_render_test.php (no framework, no composer autoload).
 *
 * Run: php tests/profile_test.php
 * Exit code 0 = all assertions passed, non-zero = at least one failure.
 */

require __DIR__ . '/../src/AvatarCropModal.php';
require __DIR__ . '/../src/ApiTokens.php';
require __DIR__ . '/../src/Profile.php';

use Erikr\Chrome\Profile;

$total  = 0;
$passed = 0;
$failures = [];

function check(bool $ok, string $label): void
{
    global $total, $passed, $failures;
    $total++;
    if ($ok) {
        $passed++;
    } else {
        $failures[] = $label;
    }
}

function assertContains(string $needle, string $haystack, string $label): void
{
    check(str_contains($haystack, $needle), $label . " (expected to contain: " . $needle . ")");
}

function assertNotContains(string $needle, string $haystack, string $label): void
{
    check(!str_contains($haystack, $needle), $label . " (expected NOT to contain: " . $needle . ")");
}

function renderProfile(array $opts): string
{
    $defaults = [
        'avatarSrc'          => '/testapp/avatar.php',
        'username'           => 'erika',
        'email'              => 'erika@example.com',
        'avatarChangeAction' => 'profil.php',
        'passwordHref'       => '/testapp/password.php',
        'csrfToken'          => 'csrf-token-xyz',
        'cspNonce'           => 'abc123',
    ];
    $cfg = array_merge($defaults, $opts);
    ob_start();
    Profile::render($cfg);
    return (string) ob_get_clean();
}

// ── 1. appSections are listed one per row, not as side-by-side pills ────
$html1 = renderProfile([
    'appSections' => [
        ['label' => 'Benachrichtigungen', 'href' => 'notifications.php'],
        ['label' => 'Geräte', 'href' => 'devices.php'],
        ['html'  => '<form method="post" action="x.php"><button>Do</button></form>'],
    ],
]);
$sectionsPos = strpos($html1, '<div class="profile-app-sections">');
check($sectionsPos !== false, '1: profile-app-sections container present');
$rowCount = substr_count($html1, '<a class="btn profile-app-section-item"')
         + substr_count($html1, '<div class="profile-app-section-item">');
check($rowCount === 3, '1: three .profile-app-section-item rows, one per appSection (' . $rowCount . ' found)');
assertContains('<a class="btn profile-app-section-item" href="notifications.php">Benachrichtigungen</a>', $html1, '1: label/href section renders as a canonical full-width .btn');
assertContains('<div class="profile-app-section-item"><form method="post" action="x.php"><button>Do</button></form></div>', $html1, '1: html section renders raw markup unescaped inside its own row');
// Regression guard: no flex/pill container class anywhere in the output
assertNotContains('pill', $html1, '1: no "pill" class anywhere (appSections are rows, not pills)');

// ── 2. Escaping of dynamic values ────────────────────────────────────────
$html2 = renderProfile([
    'username' => '<script>alert(1)</script>',
    'email'    => 'a&b<>"@example.com',
    'appSections' => [
        ['label' => '<b>Bold</b>', 'href' => 'x.php?a=1&b=2'],
    ],
]);
assertNotContains('<script>alert(1)</script>', $html2, '2: username is escaped, no raw <script>');
assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html2, '2: username escaped form present');
assertContains('a&amp;b&lt;&gt;&quot;@example.com', $html2, '2: email is escaped');
assertContains('&lt;b&gt;Bold&lt;/b&gt;', $html2, '2: appSections label is escaped (not raw HTML, unlike the html key)');
assertContains('href="x.php?a=1&amp;b=2"', $html2, '2: appSections href is escaped');

// ── 3. username has no edit control (pencil) ─────────────────────────────
$html3 = renderProfile([]);
$dtUserPos = strpos($html3, '<dt>Benutzername</dt>');
$dtEmailPos = strpos($html3, '<dt>E-Mail</dt>');
check($dtUserPos !== false && $dtEmailPos !== false && $dtUserPos < $dtEmailPos, '3: Benutzername row precedes E-Mail row');
$usernameRow = substr($html3, $dtUserPos, $dtEmailPos - $dtUserPos);
assertNotContains('ui-icon-edit', $usernameRow, '3: no pencil/edit icon in the Benutzername row');
assertNotContains('<button', $usernameRow, '3: no button at all in the Benutzername row');

// ── 4. E-Mail row has a pencil when emailEditAction is set ──────────────
$html4 = renderProfile(['emailEditAction' => 'profil.php']);
assertContains('id="profileEmailEditToggle"', $html4, '4: pencil toggle button present when emailEditAction is set');
assertContains('ui-icon-edit', $html4, '4: pencil uses ui-icon-edit (no emoji)');
assertContains('id="profileEmailForm" hidden', $html4, '4: inline e-mail form starts hidden when there is no emailError');
assertContains('name="action" value="change_email"', $html4, '4: inline form posts action=change_email');
assertContains('name="email_password"', $html4, '4: inline form has the email_password confirmation field');

// ── 4b. emailEditHref (link mode) renders a plain link, no inline form ──
$html4b = renderProfile(['emailEditHref' => '/testapp/email.php']);
assertContains('<a href="/testapp/email.php" class="btn btn-sm btn-icon"', $html4b, '4b: pencil is a plain link when only emailEditHref is set');
assertNotContains('id="profileEmailForm"', $html4b, '4b: no inline form rendered in link mode');
assertNotContains('id="profileEmailEditToggle"', $html4b, '4b: no toggle button in link mode');

// ── 4c. neither emailEditAction nor emailEditHref → no pencil at all ────
$html4c = renderProfile([]);
assertNotContains('ui-icon-edit', $html4c, '4c: no pencil rendered when neither emailEditAction nor emailEditHref is set');

// ── 4d. emailError present → inline form starts open, shows the message ──
$html4d = renderProfile(['emailEditAction' => 'profil.php', 'emailError' => 'Das Kennwort ist falsch.']);
assertContains('id="profileEmailForm">', $html4d, '4d: inline form is NOT hidden when emailError is set');
assertContains('Das Kennwort ist falsch.', $html4d, '4d: emailError message rendered');
assertContains('role="alert"', $html4d, '4d: emailError uses role="alert"');

// ── 5. CSP nonce applied to inline scripts ───────────────────────────────
$html5 = renderProfile(['cspNonce' => 'n0nce-xyz']);
check(substr_count($html5, ' nonce="n0nce-xyz"') >= 1, '5: at least one inline <script> carries the CSP nonce');

// ── 6. Page heading: default "Profil", null suppresses it, custom title is
//       escaped and rendered as the first element inside .pref-section ────
$html6 = renderProfile([]);
$prefPos6 = strpos($html6, '<div class="pref-section profile-page">');
check($prefPos6 !== false, '6: .pref-section wrapper present');
assertContains('<h1>Profil</h1>', $html6, '6: default title "Profil" renders as <h1>');
$h1Pos6 = strpos($html6, '<h1>Profil</h1>');
$avatarPos6 = strpos($html6, 'profile-avatar-block');
check($h1Pos6 !== false && $prefPos6 < $h1Pos6, '6: <h1> comes after the .pref-section opening tag');
check($h1Pos6 !== false && $avatarPos6 !== false && $h1Pos6 < $avatarPos6, '6: <h1> precedes the avatar block');

$html6b = renderProfile(['title' => null]);
assertNotContains('<h1>', $html6b, '6b: title => null suppresses the heading entirely');

$html6c = renderProfile(['title' => '<script>x</script> Mein Profil']);
assertNotContains('<h1><script>', $html6c, '6c: custom title is escaped, no raw markup');
assertContains('<h1>&lt;script&gt;x&lt;/script&gt; Mein Profil</h1>', $html6c, '6c: custom title rendered escaped');

// ── 7. avatarClearAction: second button only when set, correct button tier ─
$html7 = renderProfile([]);
assertNotContains('profileAvatarClear', $html7, '7: no "Profilbild entfernen" button when avatarClearAction is not set');

$html7b = renderProfile(['avatarClearAction' => 'profil.php']);
assertContains('id="profileAvatarClear"', $html7b, '7b: "Profilbild entfernen" button rendered when avatarClearAction is set');
assertContains('Profilbild entfernen', $html7b, '7b: button label present');
$btnPos7b = strpos($html7b, 'id="profileAvatarClear"');
$tagStart7b = strrpos(substr($html7b, 0, $btnPos7b), '<button');
$tagEnd7b = strpos($html7b, '>', $btnPos7b);
$btnTag7b = substr($html7b, $tagStart7b, $tagEnd7b - $tagStart7b);
assertContains('btn-outline-danger', $btnTag7b, '7b: button uses .btn-outline-danger (Rule §7.1, data-changing/removing, non-primary)');
assertNotContains('btn-danger"', $btnTag7b, '7b: button is NOT the primary/commit .btn-danger tier');
assertContains('clear_avatar', $html7b, '7b: JS posts action=clear_avatar');
assertContains('confirmDialog', $html7b, '7b: uses the shared confirmDialog (no native confirm())');
assertNotContains('confirm(', $html7b, '7b: no native window.confirm() call anywhere (only confirmDialog(...))');

// ── 8. No hex color fallbacks in the nonce\'d <style> block (Rule §1/§9) ────
$html8 = renderProfile(['cspNonce' => 'style-check']);
$styleStart8 = strpos($html8, '<style');
$styleEnd8 = strpos($html8, '</style>') + strlen('</style>');
$styleBlock8 = substr($html8, $styleStart8, $styleEnd8 - $styleStart8);
check(!str_contains($styleBlock8, '#'), '8: no "#" (hex color fallback) anywhere in the <style> block');
assertContains('var(--color-border)', $styleBlock8, '8: uses the --color-border token');
check(!preg_match('/(background|color)\s*:\s*(?!var\()/i', $styleBlock8),
    '8: jede Farbangabe im <style>-Block nutzt ein var(--…)-Token');

// ── 9. "Tokens verwalten" dialog: only with tokenAction, list + escaping + buttons ──
$html9 = renderProfile([]);
assertNotContains('id="apiTokensBlock"', $html9, '9: no API-Token dialog body when tokenAction is not set');
assertNotContains('id="apiTokensModal"', $html9, '9: no API-Token dialog backdrop when tokenAction is not set');
assertNotContains('id="apiTokensToggle"', $html9, '9: no "Tokens verwalten" trigger button when tokenAction is not set');
assertNotContains('Tokens verwalten', $html9, '9: no "Tokens verwalten" text anywhere when tokenAction is not set');

$tokenFixture = [
    [
        'id' => 7, 'label' => 'Mein <Handy>', 'source' => 'web',
        'created_at' => '2026-07-01 10:00:00', 'last_used_at' => '2026-07-20 08:30:00',
        'expires_at' => null,
    ],
    [
        'id' => 8, 'label' => '', 'source' => 'credentials',
        'created_at' => '2026-07-10 09:00:00', 'last_used_at' => null,
        'expires_at' => null,
    ],
];
$html9b = renderProfile(['tokenAction' => 'profil.php', 'tokens' => $tokenFixture]);
assertContains('id="apiTokensBlock"', $html9b, '9b: API-Token dialog body present when tokenAction is set');
assertContains('id="apiTokensModal"', $html9b, '9b: dialog backdrop present');
assertContains('<h2 class="app-modal-title" id="apiTokensModal-titel">Tokens verwalten</h2>', $html9b, '9b: dialog title "Tokens verwalten" present');
assertContains('data-action="profil.php"', $html9b, '9b: block carries the tokenAction POST target');

// Trigger button: "Tokens verwalten (N)" with the correct count, bare .btn,
// analogous to "Kennwort ändern", opens the dialog via data-modal-open.
assertContains('id="apiTokensToggle"', $html9b, '9b: trigger button present');
assertContains('Tokens verwalten (2)', $html9b, '9b: trigger button shows the correct token count');
assertContains('data-modal-open="apiTokensModal"', $html9b, '9b: trigger button opens the dialog by id');
$toggleBtnPos = strpos($html9b, 'id="apiTokensToggle"');
$toggleTagStart = strrpos(substr($html9b, 0, $toggleBtnPos), '<button');
$toggleTagEnd = strpos($html9b, '>', $toggleBtnPos);
$toggleBtnTag = substr($html9b, $toggleTagStart, $toggleTagEnd - $toggleTagStart);
assertContains('class="btn"', $toggleBtnTag, '9b: trigger button is a bare .btn (neutral, changes nothing by itself — Rule §7.1)');

// 0 tokens still shows the trigger button, with "(0)".
$html9e = renderProfile(['tokenAction' => 'profil.php', 'tokens' => []]);
assertContains('Tokens verwalten (0)', $html9e, '9e: trigger button shows "(0)" when the account has no tokens');

// Dialog is present in the markup but hidden initially, with the required
// a11y attributes (Rule §5).
assertContains('id="apiTokensModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="apiTokensModal-titel" hidden', $html9b, '9b: dialog backdrop starts hidden and carries role/aria-modal/aria-labelledby');

// Section order: trigger button sits right after "Kennwort ändern", dialog
// markup follows, appSections come last.
$html9c = renderProfile([
    'tokenAction' => 'profil.php',
    'tokens'      => $tokenFixture,
    'appSections' => [['label' => 'Extra', 'href' => 'extra.php']],
]);
$kennwortPos = strpos($html9c, 'Kennwort ändern');
$toggleBtnPos9c = strpos($html9c, 'id="apiTokensToggle"');
$dialogPos = strpos($html9c, 'id="apiTokensModal"');
$appSectionsPos = strpos($html9c, '<div class="profile-app-sections">');
check($kennwortPos !== false && $toggleBtnPos9c !== false && $dialogPos !== false && $appSectionsPos !== false
    && $kennwortPos < $toggleBtnPos9c && $toggleBtnPos9c < $dialogPos && $dialogPos < $appSectionsPos,
    '9c: trigger button follows Kennwort-ändern, dialog follows the button, appSections come last');

// The token list markup lives inside the dialog body (#apiTokensBlock),
// not loose in the page flow — i.e. between the dialog backdrop's opening
// tag and its matching close, not before it.
$backdropOpenPos = strpos($html9c, '<div class="app-modal-backdrop" id="apiTokensModal"');
$listPos9c = strpos($html9c, 'id="apiTokensList"');
check($backdropOpenPos !== false && $listPos9c !== false && $backdropOpenPos < $listPos9c,
    '9c: token list sits after the dialog backdrop opens (inside the dialog, not in the page flow)');

// List renders both entries, escaped, one Widerrufen button each.
assertContains('data-token-id="7"', $html9b, '9b: token id 7 rendered');
assertContains('data-token-id="8"', $html9b, '9b: token id 8 rendered');
assertNotContains('Mein <Handy>', $html9b, '9b: label is escaped, no raw markup');
assertContains('Mein &lt;Handy&gt;', $html9b, '9b: label escaped form present');
assertContains('(ohne Bezeichnung)', $html9b, '9b: empty label falls back to placeholder text');
check(substr_count($html9b, 'data-token-revoke') === 2, '9b: one Widerrufen button per token (' . substr_count($html9b, 'data-token-revoke') . ' found)');

// Widerrufen button uses .btn-outline-danger (Rule §7.1, removing/non-primary).
$revokeBtnPos = strpos($html9b, 'data-token-revoke');
$revokeTagStart = strrpos(substr($html9b, 0, $revokeBtnPos), '<button');
$revokeTagEnd = strpos($html9b, '>', $revokeBtnPos);
$revokeBtnTag = substr($html9b, $revokeTagStart, $revokeTagEnd - $revokeTagStart);
assertContains('btn-outline-danger', $revokeBtnTag, '9b: Widerrufen button uses .btn-outline-danger');
assertNotContains('btn-danger"', $revokeBtnTag, '9b: Widerrufen button is not the primary/commit .btn-danger tier');

// "Token anlegen" submit button also uses .btn-outline-danger (data-changing).
$createBtnPos = strpos($html9b, 'Token anlegen');
$createTagStart = strrpos(substr($html9b, 0, $createBtnPos), '<button');
$createTagEnd = strpos($html9b, '>', $createTagStart);
$createBtnTag = substr($html9b, $createTagStart, $createTagEnd - $createTagStart);
assertContains('btn-outline-danger', $createBtnTag, '9b: "Token anlegen" button uses .btn-outline-danger');

// Empty-state text shown when tokens list is empty (but tokenAction is set).
$html9d = renderProfile(['tokenAction' => 'profil.php', 'tokens' => []]);
assertContains('Noch keine API-Token erstellt.', $html9d, '9d: empty-state text shown with no tokens');
assertContains('id="apiTokensList" class="list-unstyled d-flex flex-column gap-2" hidden', $html9d, '9d: token list is hidden when empty');

// No cleartext token ever appears in server-rendered markup — Profile/ApiTokens
// never receive one; the reveal field starts empty and hidden.
assertContains('id="apiTokenReveal" class="app-alert app-alert-info" hidden', $html9b, '9b: reveal panel starts hidden');
assertContains('id="apiTokenRevealField" class="form-control" readonly', $html9b, '9b: reveal field starts empty (no value attribute)');

// dialog.js is loaded (module) when the token block is active, for confirmDialog.
assertContains('type="module" src="' . '/css/shared/js/dialog.js' . '"', $html9b, '9b: dialog.js loaded as a module when tokenAction is set');
assertContains('type="module" src="' . '/css/shared/js/api-tokens.js' . '"', $html9b, '9b: api-tokens.js loaded as a module');

// ── 10. Canonical buttons: no inline style="…" attributes anywhere, and no
//        size-class mixing within a button row (Rule §7.1 / UI design rules) ─
$html10 = renderProfile([
    'avatarClearAction' => 'profil.php',
    'emailEditAction'   => 'profil.php',
    'tokenAction'       => 'profil.php',
    'tokens'            => $tokenFixture,
    'appSections'       => [['label' => 'Extra', 'href' => 'extra.php']],
]);
assertNotContains('style="', $html10, '10: no inline style="…" attribute anywhere in a fully-featured render — spacing/layout comes from canonical classes only');

// Avatar row ("Profilbild ändern" + "Profilbild entfernen"): both bare-size
// (.btn / .btn-outline-danger), neither is .btn-sm — same size class in the row.
$avatarRowStart = strpos($html10, 'profile-avatar-block');
$avatarRowEnd = strpos($html10, '</div>', $avatarRowStart);
$avatarRow = substr($html10, $avatarRowStart, $avatarRowEnd - $avatarRowStart);
assertNotContains('btn-sm', $avatarRow, '10: avatar row buttons are not .btn-sm (would mix sizes with the bare .btn label)');

// "Profilbild ändern" is a real canonical .btn (label wired to the hidden
// file input — cursor comes from .btn itself, no inline cursor override needed).
assertContains('<label class="btn" for="profileAvatarFile">', $html10, '10: file-picker trigger is a canonical .btn label, no inline cursor style');

// ── 11. "Konto deaktivieren": only with deactivateAction AND non-admin ────
$html11 = renderProfile([]);
assertNotContains('Konto deaktivieren', $html11, '11: nothing rendered without deactivateAction (back-compat)');
assertNotContains('id="deactivate-modal"', $html11, '11: no dialog without deactivateAction');

$html11admin = renderProfile(['deactivateAction' => 'profil.php', 'isAdmin' => true]);
assertNotContains('Konto deaktivieren', $html11admin, '11: not rendered for admins (they would lock themselves out)');
assertNotContains('id="deactivate-modal"', $html11admin, '11: no dialog for admins');

$html11b = renderProfile(['deactivateAction' => 'profil.php', 'isAdmin' => false]);
assertContains('Konto deaktivieren', $html11b, '11b: button label present (wording is binding)');
assertContains('id="profileDeactivate"', $html11b, '11b: trigger button present');
assertContains('id="deactivate-modal"', $html11b, '11b: dialog present in the markup');

// isAdmin defaults to false — deactivateAction alone is enough.
$html11c = renderProfile(['deactivateAction' => 'profil.php']);
assertContains('id="deactivate-modal"', $html11c, '11c: isAdmin defaults to false');

// Trigger button tier: .btn-outline-danger (decided §7.1 exception, 2026-07-25).
$deacBtnPos = strpos($html11b, 'id="profileDeactivate"');
$deacBtnTag = '';
if ($deacBtnPos !== false) {
    $deacTagStart = strrpos(substr($html11b, 0, $deacBtnPos), '<button');
    $deacTagEnd = strpos($html11b, '>', $deacBtnPos);
    $deacBtnTag = substr($html11b, $deacTagStart, $deacTagEnd - $deacTagStart);
}
assertContains('btn-outline-danger', $deacBtnTag, '11b: trigger uses .btn-outline-danger (decided §7.1 exception — self-lockout, only an admin can undo it)');
assertNotContains('btn-danger"', $deacBtnTag, '11b: trigger is not the solid commit tier');
assertContains('data-modal-open="deactivate-modal"', $deacBtnTag, '11b: trigger opens the dialog via the shared openModal wiring (admin.js), no second dialog mechanism');

// Dialog: canonical .app-modal-* pattern, starts hidden, full a11y set (§5).
assertContains('<div class="app-modal-backdrop" id="deactivate-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="deactivate-modal-titel" hidden>', $html11b, '11b: canonical backdrop, hidden initially, role/aria-modal/aria-labelledby');
assertContains('<h2 class="app-modal-title" id="deactivate-modal-titel">Konto deaktivieren</h2>', $html11b, '11b: dialog title "Konto deaktivieren"');

// Consequences are spelled out: suite-wide, only an admin can undo it.
assertContains('alle', $html11b, '11b: dialog names the suite-wide scope');
assertContains('Administrator', $html11b, '11b: dialog says only an administrator can re-enable the account');

// POST contract: action, password confirmation, CSRF.
assertContains('id="deactivate-form"', $html11b, '11b: form present');
assertContains('name="action" value="deactivate_account"', $html11b, '11b: posts action=deactivate_account');
assertContains('name="password"', $html11b, '11b: password confirmation field (same pattern as the e-mail change)');
assertContains('id="deactivate-password"', $html11b, '11b: password field carries the id the label points at');
assertContains('autocomplete="current-password"', $html11b, '11b: password field uses autocomplete=current-password');
assertContains('name="csrf_token" value="csrf-token-xyz"', $html11b, '11b: CSRF token embedded in the form');
assertContains('id="deactivate-error"', $html11b, '11b: error slot present for the JS (never a bare "Fehler" — Rule §21)');
assertContains('id="deactivate-error" class="app-alert app-alert-danger" role="alert" hidden', $html11b, '11b: error slot is a hidden role=alert danger alert');

// Footer tiers: cancel neutral, confirm solid .btn-danger.
$footerPos = strpos($html11b, '<div class="app-modal-footer">');
$footer11 = $footerPos === false ? '' : substr($html11b, $footerPos);
assertContains('<button type="button" class="btn" data-modal-close>Abbrechen</button>', $footer11, '11b: cancel is a bare .btn');
assertContains('class="btn btn-danger"', $footer11, '11b: confirm is the solid .btn-danger commit tier');
assertContains('form="deactivate-form"', $footer11, '11b: confirm button submits the dialog form from the footer');

// Behaviour script is loaded by the renderer (module, like api-tokens.js).
assertContains('type="module" src="/css/shared/js/account-deactivate.js"', $html11b, '11b: account-deactivate.js loaded as a module');
// openModal/closeModal come from admin.js — loaded here when the token dialog
// (which already loads it) is not active, never twice.
assertContains('src="/css/shared/js/admin.js"', $html11b, '11b: admin.js loaded for openModal/closeModal when no token dialog is present');
$html11d = renderProfile(['deactivateAction' => 'profil.php', 'tokenAction' => 'profil.php']);
check(substr_count($html11d, '/css/shared/js/admin.js') === 1, '11d: admin.js loaded exactly once when both dialogs are present (' . substr_count($html11d, '/css/shared/js/admin.js') . ' found)');

// Position: last block, after appSections, behind a divider.
$html11e = renderProfile([
    'deactivateAction' => 'profil.php',
    'appSections'      => [['label' => 'Extra', 'href' => 'extra.php']],
]);
$appSecPos11 = strpos($html11e, '<div class="profile-app-sections">');
$deacPos11 = strpos($html11e, 'id="profileDeactivate"');
check($appSecPos11 !== false && $deacPos11 !== false && $appSecPos11 < $deacPos11, '11e: deactivate block comes after appSections (bottom of the page)');
$dividerBefore = $deacPos11 === false
    ? false
    : strrpos(substr($html11e, 0, $deacPos11), '<hr class="profile-divider">');
check($dividerBefore !== false, '11e: deactivate block sits behind a divider');

// No inline styles, no emoji, escaping.
assertNotContains('style="', $html11b, '11b: no inline style attribute in the deactivate block');
$html11f = renderProfile(['deactivateAction' => 'profil.php?a=1&b=2']);
assertContains('action="profil.php?a=1&amp;b=2"', $html11f, '11f: deactivateAction is escaped');

// ── Summary ────────────────────────────────────────────────────────────
echo "\n";
if ($failures !== []) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo "{$passed}/{$total} ok\n";
exit($passed === $total ? 0 : 1);
