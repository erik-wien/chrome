<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test für Erikr\Chrome\Admin\Dispatch — die Mail-Warnung aus
 * auth TASK-7. Kein Framework, kein Composer-Autoload, keine DB, kein Netz.
 *
 * Geprüft wird genau die Zusicherung, die vorher fehlte: Anlegen und Reset
 * gelingen auch dann (`ok: true`), wenn die E-Mail nicht rausgeht — die
 * Antwort trägt dann aber eine `warning` mit der Ursache. Vorher meldete die
 * Userverwaltung blanken Erfolg (Anlegen) bzw. „Fehler beim Reset." ohne Grund,
 * obwohl der Reset gelaufen war.
 *
 * Die erikr/auth-Funktionen werden hier als globale Stubs definiert — Dispatch
 * ruft sie über den globalen Namensraum auf.
 *
 * Run: php tests/dispatch_mail_warning_test.php
 * Exit code 0 = alle Assertions bestanden.
 */

// ── Steuerung der Stubs ──────────────────────────────────────────────────

$GLOBALS['stub_mail_sent']  = true;
$GLOBALS['stub_mail_error'] = null;
$GLOBALS['stub_user_found'] = true;

function csrf_verify(): bool { return true; }
function appendLog(mysqli $con, string $k, string $a, ?string $o = null): void {}

function admin_create_user(
    mysqli $con,
    string $username,
    string $email,
    string $rights,
    string $baseUrl,
    ?array &$mail = null
): int {
    $mail = ['sent' => $GLOBALS['stub_mail_sent'], 'error' => $GLOBALS['stub_mail_error']];
    return 42;
}

function admin_reset_password(mysqli $con, int $id, string $baseUrl): array
{
    if (!$GLOBALS['stub_user_found']) {
        return ['ok' => false, 'unblocked_ips' => [], 'mail_sent' => false, 'mail_error' => null];
    }
    return [
        'ok'            => true,
        'unblocked_ips' => ['203.0.113.7'],
        'mail_sent'     => $GLOBALS['stub_mail_sent'],
        'mail_error'    => $GLOBALS['stub_mail_error'],
    ];
}

require __DIR__ . '/../src/Admin/Dispatch.php';

use Erikr\Chrome\Admin\Dispatch;

/** Fake mysqli, das nie verbindet (Muster aus activity_test.php). */
final class FakeCon extends \mysqli
{
    public function __construct() {}
}

// ── Mini-Harness ─────────────────────────────────────────────────────────

$fehler = 0;
$ok     = 0;
function check(bool $bedingung, string $name): void
{
    global $fehler, $ok;
    if ($bedingung) { $ok++; echo "  ✓ $name\n"; }
    else            { $fehler++; echo "  ✗ $name\n"; }
}

/**
 * Führt eine Aktion aus und liefert die dekodierte JSON-Antwort.
 */
function ruf(string $action, array $post): array
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['rights']        = 'Admin';
    $_SESSION['id']            = 1;
    $_POST                     = $post;
    // Dispatch setzt Header und Statuscode. Auf der CLI hat der Harness schon
    // etwas ausgegeben, also warnt PHP („headers already sent") — die Warnung
    // landete sonst IM Ausgabepuffer und machte das JSON unlesbar. Für die
    // Dauer des Aufrufs stillgelegt; die Antwort selbst ist davon unberührt.
    set_error_handler(static fn(): bool => true);
    ob_start();
    try {
        Dispatch::handle(new FakeCon(), $action, ['baseUrl' => 'https://example.test', 'selfId' => 1]);
        $roh = (string) ob_get_clean();
    } finally {
        restore_error_handler();
    }
    return json_decode($roh, true) ?? [];
}

echo "\nDispatch — Mail-Warnung (auth TASK-7)\n\n";

// ── Anlegen ──────────────────────────────────────────────────────────────

$GLOBALS['stub_mail_sent']  = false;
$GLOBALS['stub_mail_error'] = 'MailConfigException: Mail config not found';
$r = ruf('admin_user_create', ['username' => 'alice', 'email' => 'alice@example.test', 'rights' => 'User']);

check(($r['ok'] ?? false) === true, 'Anlegen: ok bleibt true — das Konto existiert (AC#3)');
check(($r['id'] ?? 0) === 42, 'Anlegen: die ID kommt weiterhin zurück');
check(isset($r['warning']), 'Anlegen: bei Mailfehler trägt die Antwort eine Warnung');
check(str_contains($r['warning'] ?? '', 'Mail config not found'),
      'Anlegen: die Warnung nennt die Ursache, nicht nur „Fehler" (§21)');
check(str_contains($r['warning'] ?? '', 'alice@example.test'),
      'Anlegen: die Warnung nennt die betroffene Adresse');

$GLOBALS['stub_mail_sent']  = true;
$GLOBALS['stub_mail_error'] = null;
$r = ruf('admin_user_create', ['username' => 'bob', 'email' => 'bob@example.test', 'rights' => 'User']);

check(($r['ok'] ?? false) === true, 'Anlegen: Erfolgsfall bleibt ok');
check(!isset($r['warning']), 'Anlegen: ohne Mailfehler KEINE Warnung (sonst Fehlalarm bei jedem Anlegen)');

// ── Passwort-Reset ───────────────────────────────────────────────────────

$GLOBALS['stub_mail_sent']  = false;
$GLOBALS['stub_mail_error'] = 'SMTP Error: Could not authenticate.';
$r = ruf('admin_user_reset', ['id' => '7']);

check(($r['ok'] ?? false) === true,
      'Reset: ok true trotz Mailfehler — Token ausgestellt, IPs entsperrt (AC#3)');
check(($r['unblocked_ips'] ?? []) === ['203.0.113.7'], 'Reset: entsperrte IPs bleiben erhalten');
check(isset($r['warning']), 'Reset: bei Mailfehler trägt die Antwort eine Warnung');
check(str_contains($r['warning'] ?? '', 'Could not authenticate'),
      'Reset: die Warnung nennt die Ursache');

$GLOBALS['stub_mail_sent']  = true;
$GLOBALS['stub_mail_error'] = null;
$r = ruf('admin_user_reset', ['id' => '7']);
check(($r['ok'] ?? false) === true && !isset($r['warning']),
      'Reset: ohne Mailfehler ok und keine Warnung');

$GLOBALS['stub_user_found'] = false;
$r = ruf('admin_user_reset', ['id' => '999']);
check(($r['ok'] ?? true) === false, 'Reset: unbekannter Benutzer bleibt ok=false');
check(!isset($r['warning']),
      'Reset: unbekannter Benutzer erzeugt KEINE Mailwarnung — es wurde nichts versucht');

echo "\n" . ($fehler === 0 ? "✓ $ok/$ok bestanden.\n" : "✗ $fehler von " . ($ok + $fehler) . " fehlgeschlagen.\n");
exit($fehler === 0 ? 0 : 1);
