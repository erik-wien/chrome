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
$rowCount = substr_count($html1, '<div class="list-group-item">');
check($rowCount === 3, '1: three .list-group-item rows, one per appSection (' . $rowCount . ' found)');
assertContains('<div class="list-group-item"><a href="notifications.php">Benachrichtigungen</a></div>', $html1, '1: label/href section renders as a full-width link row');
assertContains('<div class="list-group-item"><form method="post" action="x.php"><button>Do</button></form></div>', $html1, '1: html section renders raw markup unescaped inside its own row');
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
