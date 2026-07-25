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

// ── 9. API-Token block: only with tokenAction, list + escaping + buttons ──
$html9 = renderProfile([]);
assertNotContains('id="apiTokensBlock"', $html9, '9: no API-Token block when tokenAction is not set');
assertNotContains('API-Token', $html9, '9: no "API-Token" heading when tokenAction is not set');

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
assertContains('id="apiTokensBlock"', $html9b, '9b: API-Token block present when tokenAction is set');
assertContains('<h2>API-Token</h2>', $html9b, '9b: "API-Token" heading present');
assertContains('data-action="profil.php"', $html9b, '9b: block carries the tokenAction POST target');

// Section order: heading comes after "Kennwort ändern", before appSections.
$html9c = renderProfile([
    'tokenAction' => 'profil.php',
    'tokens'      => $tokenFixture,
    'appSections' => [['label' => 'Extra', 'href' => 'extra.php']],
]);
$kennwortPos = strpos($html9c, 'Kennwort ändern');
$tokenHeadingPos = strpos($html9c, '<h2>API-Token</h2>');
$appSectionsPos = strpos($html9c, '<div class="profile-app-sections">');
check($kennwortPos !== false && $tokenHeadingPos !== false && $appSectionsPos !== false
    && $kennwortPos < $tokenHeadingPos && $tokenHeadingPos < $appSectionsPos,
    '9c: API-Token section sits after Kennwort-ändern and before appSections');

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
