<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test for Erikr\Chrome\Status — no framework, no composer
 * autoload needed.
 *
 * Run: php tests/status_test.php
 * Exit code 0 = all assertions passed, non-zero = at least one failure.
 */

require __DIR__ . '/../src/Status.php';

use Erikr\Chrome\Status;

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

$tmpDir = sys_get_temp_dir() . '/chrome-status-test-' . getmypid();
@mkdir($tmpDir, 0775, true);

// ── 1. run(): ok/warn/fail/Exception-Check ────────────────────────────────
$checks1 = [
    ['name' => 'OK-Check',   'check' => fn() => ['state' => 'ok', 'last_success_ts' => 1000]],
    ['name' => 'Warn-Check', 'check' => fn() => ['state' => 'warn', 'detail' => 'veraltet']],
    ['name' => 'Fail-Check', 'check' => fn() => ['state' => 'fail', 'detail' => 'kaputt']],
    ['name' => 'Boom-Check', 'check' => function () { throw new RuntimeException('boom'); }],
];
$r1 = Status::run($checks1);
check(count($r1['checks']) === 4, '1: run() liefert 4 Ergebnisse');
check($r1['checks'][0]['state'] === 'ok', '1: OK-Check state=ok');
check($r1['checks'][0]['last_success_ts'] === 1000, '1: OK-Check last_success_ts durchgereicht');
check($r1['checks'][1]['state'] === 'warn', '1: Warn-Check state=warn');
check($r1['checks'][2]['state'] === 'fail', '1: Fail-Check state=fail');
check($r1['checks'][3]['state'] === 'fail', '1: Exception im Check => state=fail');
check($r1['checks'][3]['detail'] === 'boom', '1: Exception-Message landet in detail');
check(is_int($r1['generated_ts']) && $r1['generated_ts'] > 0, '1: generated_ts gesetzt');
foreach ($r1['checks'] as $c) {
    check(isset($c['duration_ms']) && $c['duration_ms'] >= 0, '1: duration_ms pro Check gemessen (' . $c['name'] . ')');
}

// ── 2. Cache-Hit: zweiter run() ruft das Callable NICHT erneut auf ───────
$cacheFile2 = $tmpDir . '/cache_hit.json';
$calls2 = 0;
$checks2 = [
    ['name' => 'Counted', 'check' => function () use (&$calls2) { $calls2++; return ['state' => 'ok']; }],
];
$rA = Status::run($checks2, ['cacheFile' => $cacheFile2, 'cacheTtl' => 60]);
$rB = Status::run($checks2, ['cacheFile' => $cacheFile2, 'cacheTtl' => 60]);
check($calls2 === 1, '2: Check-Callable läuft bei Cache-Hit nur einmal (war: ' . $calls2 . ')');
check($rA['generated_ts'] === $rB['generated_ts'], '2: Cache-Hit liefert identisches generated_ts');
check(is_file($cacheFile2), '2: Cache-Datei wurde angelegt');

// ── 3. Cache-Ablauf via manipuliertem generated_ts im Cache-Inhalt → Check läuft erneut ──
// TTL-Quelle ist generated_ts im JSON, nicht filemtime() (vgl. 3c: mtime bleibt frisch).
$cacheFile3 = $tmpDir . '/cache_expired.json';
$calls3 = 0;
$checks3 = [
    ['name' => 'Counted', 'check' => function () use (&$calls3) { $calls3++; return ['state' => 'ok']; }],
];
Status::run($checks3, ['cacheFile' => $cacheFile3, 'cacheTtl' => 60]);
check($calls3 === 1, '3: erster run() führt den Check aus');
$cached3 = json_decode((string) file_get_contents($cacheFile3), true);
$cached3['generated_ts'] = time() - 3600; // im Inhalt weit in die Vergangenheit -> Cache abgelaufen
file_put_contents($cacheFile3, json_encode($cached3));
Status::run($checks3, ['cacheFile' => $cacheFile3, 'cacheTtl' => 60]);
check($calls3 === 2, '3: abgelaufener Cache (generated_ts) -> Check läuft erneut (war: ' . $calls3 . ')');

// ── 3b. Kaputtes/unlesbares Cache-JSON → run() crasht nicht, führt Check neu aus ──
$cacheFile3b = $tmpDir . '/cache_broken.json';
file_put_contents($cacheFile3b, 'not json');
$calls3b = 0;
$checks3b = [
    ['name' => 'Counted', 'check' => function () use (&$calls3b) { $calls3b++; return ['state' => 'ok']; }],
];
$r3b = Status::run($checks3b, ['cacheFile' => $cacheFile3b, 'cacheTtl' => 60]);
check($calls3b === 1, '3b: kaputtes Cache-JSON -> run() führt Check aus statt zu crashen');
check($r3b['checks'][0]['state'] === 'ok', '3b: Ergebnis trotz kaputtem Cache korrekt');

// ── 3c. TTL-Prüfung nutzt generated_ts aus dem Cache-Inhalt, nicht filemtime() ──
$cacheFile3c = $tmpDir . '/cache_stale_ts.json';
$calls3c = 0;
$checks3c = [
    ['name' => 'Counted', 'check' => function () use (&$calls3c) { $calls3c++; return ['state' => 'ok']; }],
];
// Cache-Datei mit frischer mtime, aber altem generated_ts im Inhalt schreiben.
file_put_contents($cacheFile3c, json_encode([
    'generated_ts' => time() - 3600,
    'checks'       => [['name' => 'Counted', 'state' => 'ok', 'detail' => null, 'last_success_ts' => null, 'duration_ms' => 0, 'adminOnly' => false]],
]));
Status::run($checks3c, ['cacheFile' => $cacheFile3c, 'cacheTtl' => 60]);
check($calls3c === 1, '3c: altes generated_ts im Cache-Inhalt (trotz frischer mtime) -> Check läuft erneut');

// ── 3d. Ungültiger state-Wert aus Check-Callable -> normalisiert auf fail ──
$checks3d = [
    ['name' => 'Weird-Check', 'check' => fn() => ['state' => 'weird']],
];
$r3d = Status::run($checks3d);
check($r3d['checks'][0]['state'] === 'fail', '3d: run() normalisiert ungültigen state ("weird") auf fail');

$results3d = [
    'generated_ts' => 1700000000,
    'checks' => [
        ['name' => 'Weird-Check', 'state' => 'weird', 'detail' => null, 'last_success_ts' => null, 'adminOnly' => false],
    ],
];
$json3d = Status::json($results3d, ['app' => 'testapp']);
$decoded3d = json_decode($json3d, true);
check($decoded3d['checks'][0]['state'] === 'fail', '3d: json() normalisiert ungültigen state ("weird") auf fail');

// ── 4. adminOnly-Filterung in render(): User sieht Check nicht, Admin ja ──
$results4 = [
    'generated_ts' => 1700000000,
    'checks' => [
        ['name' => 'Public-Check',  'state' => 'ok',   'detail' => null,       'last_success_ts' => 1699999000, 'adminOnly' => false],
        ['name' => 'Secret-Check',  'state' => 'fail',  'detail' => 'geheim!!', 'last_success_ts' => null,       'adminOnly' => true],
    ],
];
ob_start();
Status::render($results4, false, ['cspNonce' => 'n1']);
$htmlUser = (string) ob_get_clean();
ob_start();
Status::render($results4, true, ['cspNonce' => 'n1']);
$htmlAdmin = (string) ob_get_clean();

assertContains('Public-Check', $htmlUser, '4: User sieht Public-Check');
assertNotContains('Secret-Check', $htmlUser, '4: User sieht Secret-Check (adminOnly) NICHT');
assertContains('Public-Check', $htmlAdmin, '4: Admin sieht Public-Check');
assertContains('Secret-Check', $htmlAdmin, '4: Admin sieht Secret-Check (adminOnly)');

// ── 5. detail nur für Admin (auch bei nicht-adminOnly-Checks) ────────────
$results5 = [
    'generated_ts' => 1700000000,
    'checks' => [
        ['name' => 'X', 'state' => 'fail', 'detail' => 'interner Hostname xyz.internal', 'last_success_ts' => null, 'adminOnly' => false],
    ],
];
ob_start();
Status::render($results5, false);
$html5User = (string) ob_get_clean();
ob_start();
Status::render($results5, true);
$html5Admin = (string) ob_get_clean();
assertNotContains('xyz.internal', $html5User, '5: detail wird Nicht-Admins nicht angezeigt');
assertContains('xyz.internal', $html5Admin, '5: detail wird Admins angezeigt');

// ── 6. Zeitstempel + generated_ts-Hinweis im HTML ─────────────────────────
assertContains('zuletzt ok:', $htmlUser, '6: "zuletzt ok:"-Hinweis für Check mit last_success_ts');
assertContains('Cache max. 60 s', $htmlUser, '6: generated_ts-Hinweis mit Cache-TTL im HTML');
assertContains('status-dot--ok', $htmlUser, '6: CSS-Ampel-Klasse ok gerendert');
assertContains('status-dot--fail', $htmlAdmin, '6: CSS-Ampel-Klasse fail gerendert');
assertNotContains('🔴', $htmlUser, '6: keine Emojis im gerenderten HTML');
assertNotContains('🟢', $htmlUser, '6: keine Emojis im gerenderten HTML');

// ── 7. json(): exakte Struktur, ohne detail, ohne adminOnly-Checks ───────
$jsonStr = Status::json($results4, ['app' => 'testapp']);
$decoded = json_decode($jsonStr, true);
check(is_array($decoded), '7: json() liefert valides JSON');
check($decoded['app'] === 'testapp', '7: json() app-Feld korrekt');
check($decoded['generated_ts'] === 1700000000, '7: json() generated_ts korrekt');
check(count($decoded['checks']) === 1, '7: json() enthält nur den Nicht-adminOnly-Check');
check($decoded['checks'][0]['name'] === 'Public-Check', '7: json() Check-Name korrekt');
check($decoded['checks'][0]['state'] === 'ok', '7: json() Check-State korrekt');
check($decoded['checks'][0]['last_success_ts'] === 1699999000, '7: json() last_success_ts korrekt');
check(array_keys($decoded['checks'][0]) === ['name', 'state', 'last_success_ts'], '7: json() Check-Objekt hat exakt die 3 erwarteten Felder');
assertNotContains('geheim', $jsonStr, '7: json() enthält keinerlei detail-Text (auch nicht von adminOnly-Checks)');
assertNotContains('Secret-Check', $jsonStr, '7: json() enthält den adminOnly-Check-Namen nicht');
assertNotContains('"detail"', $jsonStr, '7: json() enthält gar kein "detail"-Feld');

// ── 8. httpCheck() gegen ungültige URL => fail (kein Netz-Test) ──────────
$httpResult = Status::httpCheck('invalid-scheme://this-does-not-resolve', 1);
check($httpResult['state'] === 'fail', '8: httpCheck() gegen ungültiges Schema => fail');
check(!empty($httpResult['detail']), '8: httpCheck() liefert eine detail-Fehlermeldung');

// ── 9. dbCheck(): Exception => fail, false-Return => fail, sonst ok ──────
$dbFail = Status::dbCheck(function () { throw new RuntimeException('DB weg'); });
check($dbFail['state'] === 'fail' && $dbFail['detail'] === 'DB weg', '9: dbCheck() Exception => fail mit Message');
$dbFalse = Status::dbCheck(fn() => false);
check($dbFalse['state'] === 'fail', '9: dbCheck() false-Return => fail');
$dbOk = Status::dbCheck(fn() => true, 'verbunden');
check($dbOk['state'] === 'ok' && $dbOk['detail'] === 'verbunden', '9: dbCheck() true-Return => ok mit okDetail');

// ── Summary ────────────────────────────────────────────────────────────
// Cleanup temp dir
foreach (glob($tmpDir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($tmpDir);

echo "\n";
if ($failures !== []) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo "{$passed}/{$total} ok\n";
exit($passed === $total ? 0 : 1);
