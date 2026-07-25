<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test for Erikr\Chrome\Header::render() — no framework, no
 * composer autoload needed (Header.php has no external deps at runtime
 * besides the optional admin_is_impersonating() guard, which is skipped
 * when the function doesn't exist).
 *
 * Run: php tests/header_render_test.php
 * Exit code 0 = all assertions passed, non-zero = at least one failure.
 */

require __DIR__ . '/../src/Header.php';
require __DIR__ . '/../src/AppsMenu.php';

use Erikr\Chrome\Header;
use Erikr\Chrome\AppsMenu;

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

/**
 * Render Header::render() with sane defaults, merged/overridden by $opts,
 * and capture the emitted HTML.
 */
function renderHeader(array $opts): string
{
    $defaults = [
        'appName'   => 'TestApp',
        'base'      => '/testapp',
        'cspNonce'  => 'abc123',
        'csrfToken' => 'csrf-token-xyz',
        'loggedIn'  => false,
        'isAdmin'   => false,
        'username'  => 'erika',
        'theme'     => 'auto',
        // Suppress forms.js script tag noise — irrelevant to these assertions.
        'formsJsPath' => null,
    ];
    $a = array_merge($defaults, $opts);
    ob_start();
    Header::render($a);
    return (string) ob_get_clean();
}

/** Substring of the full markup from the user-dropdown onward (or '' if absent). */
function userDropdownSection(string $html): string
{
    $pos = strpos($html, '<div class="user-dropdown"');
    return $pos === false ? '' : substr($html, $pos);
}

/** Substring of the full markup covering only <nav class="header-nav">…</nav>. */
function headerNavSection(string $html): string
{
    $start = strpos($html, '<nav class="header-nav">');
    if ($start === false) {
        return '';
    }
    $end = strpos($html, '</nav>', $start);
    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

$appsMenuJardyx = [
    ['href' => 'https://energie.jardyx.com', 'label' => 'Energie'],
    ['href' => 'https://zeit.jardyx.com',    'label' => 'Zeit'],
];

// ── 1. appsMenu ohne appMenu → Apps-Dropdown-Trigger, keine flachen Links ──
$html1 = renderHeader([
    'appMenu'  => [],
    'appsMenu' => $appsMenuJardyx,
]);
assertContains('expanded="false">Apps<svg', headerNavSection($html1), '1: Apps-Dropdown-Trigger "Apps" im header-nav');
assertNotContains('<nav class="header-nav"><a href="https://energie.jardyx.com"', $html1, '1: Cross-App-Links nicht flach direkt im header-nav');
assertContains('<div class="header-dropdown-panel"><a href="https://energie.jardyx.com">Energie</a>', $html1, '1: Energie-Link innerhalb des Dropdown-Panels');

// ── 2. appMenu + appsMenu → weiterhin Apps-Dropdown, kein "Links"-Label ───
$html2 = renderHeader([
    'appMenu'  => [['href' => 'daily.php', 'label' => 'Aktuell', 'type' => 'daily']],
    'appsMenu' => $appsMenuJardyx,
]);
assertContains('expanded="false">Apps<svg', headerNavSection($html2), '2: Apps-Dropdown-Trigger auch mit appMenu vorhanden');
assertNotContains('>Links<', $html2, '2: kein "Links"-Label mehr im gesamten Output');
assertNotContains('"header-dropdown-trigger" aria-haspopup="menu" aria-expanded="false">Links', $html2, '2: kein "Links"-Dropdown-Trigger');

// ── 3. isAdmin=true → Administration-Dropdown in header-nav, nicht im user-dropdown, "Verwaltung" als Kind ──
$html3 = renderHeader([
    'loggedIn' => true,
    'isAdmin'  => true,
    'appMenu'  => [],
    'appsMenu' => [],
]);
assertContains('expanded="false">Administration<svg', headerNavSection($html3), '3: Administration-Dropdown-Trigger in header-nav');
assertContains('>Verwaltung</a>', headerNavSection($html3), '3: Kind "Verwaltung" im Administration-Dropdown');
// The old flat Account-section entry (`<a … class="dropdown-link-btn">Administration</a>`)
// must be gone. Note: the mobile drill-down mirror (Test 7) legitimately reuses the word
// "Administration" as a dd-trigger label and dd-back button inside .user-dropdown — that's
// a different markup shape and intentional (Suite-Policy §mobile), not the removed link.
assertNotContains('class="dropdown-link-btn">Administration</a>', userDropdownSection($html3), '3: kein flacher "Administration"-Link im user-dropdown (Account-Sektion)');

// ── 4. isAdmin=false + adminItems (rollen-gated) → Administration-Dropdown mit Item, ohne "Verwaltung" ──
$html4 = renderHeader([
    'loggedIn'   => true,
    'isAdmin'    => false,
    'adminItems' => [['href' => '/sap-import.php', 'label' => 'SAP-Import']],
]);
assertContains('expanded="false">Administration<svg', headerNavSection($html4), '4: Administration-Dropdown-Trigger trotz isAdmin=false (adminItems)');
assertContains('>SAP-Import</a>', headerNavSection($html4), '4: SAP-Import-Item im Administration-Dropdown');
assertNotContains('>Verwaltung</a>', headerNavSection($html4), '4: kein "Verwaltung"-Kind ohne isAdmin');

// ── 5. isAdmin=false, adminItems leer → kein Administration-Dropdown ──────
$html5 = renderHeader([
    'loggedIn'   => true,
    'isAdmin'    => false,
    'adminItems' => [],
]);
assertNotContains('Administration', $html5, '5: kein Administration-Dropdown irgendwo im Output');

// ── 6. statusHref Default → "Status"-Link; statusHref=null → kein Status-Link ──
$html6a = renderHeader(['loggedIn' => true]);
assertContains('class="dropdown-link-btn">Status</a>', userDropdownSection($html6a), '6a: Status-Link im user-dropdown (Default statusHref)');
assertContains('href="/testapp/status.php"', userDropdownSection($html6a), '6a: Status-Link zeigt auf $base/status.php');

$html6b = renderHeader(['loggedIn' => true, 'statusHref' => null]);
assertNotContains('class="dropdown-link-btn">Status</a>', userDropdownSection($html6b), '6b: kein Status-Link wenn statusHref=null');

// ── 7. Mobile-Spiegelung: Drilldown-Trigger für Administration und Apps im dropdown-nav-section ──
$html7 = renderHeader([
    'loggedIn' => true,
    'isAdmin'  => true,
    'appMenu'  => [],
    'appsMenu' => $appsMenuJardyx,
]);
$navSection7pos = strpos($html7, '<div class="dropdown-nav-section">');
$navSection7end = $navSection7pos !== false ? strpos($html7, '<div class="dropdown-divider"></div>', $navSection7pos) : false;
$navSection7 = ($navSection7pos !== false && $navSection7end !== false)
    ? substr($html7, $navSection7pos, $navSection7end - $navSection7pos)
    : '';
assertContains('data-target="dd-sub-core-administration"', $navSection7, '7: Administration-Drilldown-Trigger in dropdown-nav-section');
assertContains('data-target="dd-sub-core-apps"', $navSection7, '7: Apps-Drilldown-Trigger in dropdown-nav-section');
assertContains('<div class="dd-sub" id="dd-sub-core-administration">', $html7, '7: dd-sub-Panel für Administration vorhanden');
assertContains('<div class="dd-sub" id="dd-sub-core-apps">', $html7, '7: dd-sub-Panel für Apps vorhanden');

// ── 8. AppsMenu::build('energie','local') → keine .test-URLs ─────────────
$appsMenuBuilt = AppsMenu::build('energie', 'local');
$appsMenuBuiltJson = json_encode($appsMenuBuilt);
assertNotContains('.test', (string) $appsMenuBuiltJson, '8: AppsMenu::build enthält keine .test-URLs (auch nicht im local-Env)');
check(count($appsMenuBuilt) === 6, '8: AppsMenu::build liefert die 6 übrigen Suite-Apps (ohne self)');

// ── 9. appsMenu => [] ohne appMenu → kein leeres "Apps"-Dropdown, kein <nav> ──
$html9 = renderHeader([
    'appMenu'  => [],
    'appsMenu' => [],
]);
assertNotContains('<nav class="header-nav">', $html9, '9: kein <nav> im Output, wenn appMenu und appsMenu beide leer sind');
assertNotContains('>Apps<svg', $html9, '9: kein leerer "Apps"-Dropdown-Trigger');

// ── 10. appMenu-Item mit Label "Apps" (children) + appsMenu → keine doppelte
//        dd-sub-ID (Regressionstest für den Slug-Kollisions-Bug, Review-Befund 1) ──
$html10 = renderHeader([
    'loggedIn' => true,
    'appMenu'  => [['label' => 'Apps', 'children' => [['href' => '/x', 'label' => 'X']]]],
    'appsMenu' => $appsMenuJardyx,
]);
check(substr_count($html10, 'id="dd-sub-core-apps"') === 1, '10: System-Panel "dd-sub-core-apps" (Cross-App-Links) genau einmal im Output');
check(substr_count($html10, 'id="dd-sub-apps"') === 1, '10: Label-generiertes Panel "dd-sub-apps" (appMenu-Item "Apps") genau einmal im Output');
assertContains('data-target="dd-sub-core-apps"', $html10, '10: Drilldown-Trigger für Cross-App-Links zeigt auf das System-Panel');
assertContains('data-target="dd-sub-apps"', $html10, '10: Drilldown-Trigger für das appMenu-Item "Apps" zeigt auf das Label-Panel');

// ── 11. Slim dropdown: Profil, Thema-Label, Status, Log, Hilfe, Abmelden ──
$html11 = renderHeader(['loggedIn' => true]);
$dd11 = userDropdownSection($html11);
assertContains('class="dropdown-link-btn">Profil</a>', $dd11, '11: "Profil"-Link im Dropdown');
assertContains('<span class="dropdown-section-label">Thema</span>', $dd11, '11: "Thema"-Label vor der Theme-Pille');
assertContains('class="theme-row"', $dd11, '11: Theme-Pille im Dropdown');
assertContains('class="dropdown-link-btn">Status</a>', $dd11, '11: "Status"-Link im Dropdown');
assertContains('class="dropdown-link-btn">Log</a>', $dd11, '11: "Log"-Link im Dropdown');
assertContains('class="dropdown-link-btn">Hilfe</a>', $dd11, '11: "Hilfe"-Link im Dropdown');
assertContains('>Abmelden</button>', $dd11, '11: "Abmelden"-Button im Dropdown');

// ── 12. Entfernte Gruppen sind komplett weg ──────────────────────────────
assertNotContains('Benutzereinstellungen', $dd11, '12: kein "Benutzereinstellungen"-Label mehr');
assertNotContains('>Profilbild</a>', $dd11, '12: kein "Profilbild"-Link mehr');
assertNotContains('>E-Mail</a>', $dd11, '12: kein "E-Mail"-Link mehr');
assertNotContains('>Sicherheit</a>', $dd11, '12: kein "Sicherheit"-Link mehr');
assertNotContains('>Anwendung</a>', $dd11, '12: kein "Anwendung"/appPrefs-Link mehr');
assertNotContains('>Konto</span>', $dd11, '12: kein Legacy-Flat-"Konto"-Label mehr');
assertNotContains('>Einstellungen</a>', $dd11, '12: kein Legacy-"Einstellungen"-Link mehr');
assertNotContains('Passwort &amp; 2FA', $dd11, '12: kein Legacy-"Passwort & 2FA"-Link mehr');

// ── 13. profilHref/activityHref = null → jeweiliger Eintrag fehlt ────────
$html13 = renderHeader(['loggedIn' => true, 'profilHref' => null, 'activityHref' => null]);
$dd13 = userDropdownSection($html13);
assertNotContains('class="dropdown-link-btn">Profil</a>', $dd13, '13: kein "Profil"-Link wenn profilHref=null');
assertNotContains('class="dropdown-link-btn">Log</a>', $dd13, '13: kein "Log"-Link wenn activityHref=null');
// Status/Hilfe (unrelated options) bleiben unberührt
assertContains('class="dropdown-link-btn">Status</a>', $dd13, '13: Status-Link bleibt trotz profilHref/activityHref=null');

// ── 14. Alte Optionen (profileHref etc.) werden entgegengenommen, aber nicht gerendert ──
$html14 = renderHeader([
    'loggedIn'      => true,
    'profileHref'   => '/testapp/profilbild.php',
    'emailHref'     => '/testapp/email.php',
    'securityHref'  => '/testapp/sicherheit.php',
    'appPrefsHref'  => '/testapp/anwendung.php',
    'appPrefsLabel' => 'Meine Anwendung',
    'prefsHref'     => '/testapp/einstellungen.php',
]);
$dd14 = userDropdownSection($html14);
assertNotContains('profilbild.php', $html14, '14: profileHref (deprecated) wird nicht gerendert');
assertNotContains('email.php', $html14, '14: emailHref (deprecated) wird nicht gerendert');
assertNotContains('sicherheit.php', $html14, '14: securityHref (deprecated) wird nicht gerendert');
assertNotContains('anwendung.php', $html14, '14: appPrefsHref (deprecated) wird nicht gerendert');
assertNotContains('Meine Anwendung', $html14, '14: appPrefsLabel (deprecated) wird nicht gerendert');
assertNotContains('einstellungen.php', $html14, '14: prefsHref (deprecated) wird nicht gerendert');
// Aber die schlanke Dropdown-Struktur bleibt intakt (kein Fehler, kein leerer Output)
assertContains('class="dropdown-link-btn">Profil</a>', $dd14, '14: reguläres "Profil" trotz übergebener Alt-Optionen weiterhin da');

// ── 15. Mobile-Spiegelung bleibt bei aktiviertem Slim-Dropdown unverändert ──
$html15 = renderHeader([
    'loggedIn' => true,
    'isAdmin'  => true,
    'appMenu'  => [],
    'appsMenu' => $appsMenuJardyx,
]);
$navSection15pos = strpos($html15, '<div class="dropdown-nav-section">');
$navSection15end = $navSection15pos !== false ? strpos($html15, '<div class="dropdown-divider"></div>', $navSection15pos) : false;
$navSection15 = ($navSection15pos !== false && $navSection15end !== false)
    ? substr($html15, $navSection15pos, $navSection15end - $navSection15pos)
    : '';
assertContains('data-target="dd-sub-core-administration"', $navSection15, '15: Administration-Drilldown-Trigger unverändert vorhanden');
assertContains('data-target="dd-sub-core-apps"', $navSection15, '15: Apps-Drilldown-Trigger unverändert vorhanden');
assertContains('<div class="dd-sub" id="dd-sub-core-administration">', $html15, '15: dd-sub-Panel für Administration unverändert vorhanden');
assertContains('<div class="dd-sub" id="dd-sub-core-apps">', $html15, '15: dd-sub-Panel für Apps unverändert vorhanden');

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
