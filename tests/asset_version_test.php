<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test für Erikr\Chrome\AssetVersion (css_library TASK-12).
 *
 * Der Kern: der Scan muss symlinkte Verzeichnisse betreten. Genau dort lag der
 * Fehler — `web/css/shared` ist in der Entwicklung ein Symlink auf
 * ~/Git/css_library, und ohne FOLLOW_SYMLINKS blieb eine Änderung an der
 * geteilten JS-Datei für die Kennung unsichtbar.
 *
 * Arbeitet in einem temporären Verzeichnisbaum, kein Zugriff auf das Repo.
 *
 * Run: php tests/asset_version_test.php
 */

require __DIR__ . '/../src/AssetVersion.php';

use Erikr\Chrome\AssetVersion;

$fehler = 0;
$ok     = 0;
function check(bool $b, string $name): void
{
    global $fehler, $ok;
    if ($b) { $ok++; echo "  ✓ $name\n"; }
    else    { $fehler++; echo "  ✗ $name\n"; }
}

// ── Testbaum: app/web/{css,js} + app/web/css/shared -> extern/ ───────────
$wurzel = sys_get_temp_dir() . '/assetver_' . getmypid();
$web    = "$wurzel/app/web";
$extern = "$wurzel/extern";
mkdir("$web/css", 0777, true);
mkdir("$web/js", 0777, true);
mkdir($extern, 0777, true);

file_put_contents("$web/css/app.css", 'a{}');
file_put_contents("$web/js/app.js", '// x');
file_put_contents("$extern/shared.css", 'b{}');
file_put_contents("$extern/nicht-relevant.txt", 'egal');
symlink($extern, "$web/css/shared");

$alt = 1_700_000_000;   // fix, damit der Test nicht von der Uhr abhängt
touch("$web/css/app.css", $alt);
touch("$web/js/app.js", $alt);
touch("$extern/shared.css", $alt);

echo "\nAssetVersion (css_library TASK-12)\n\n";

$dirs = ["$web/css", "$web/js"];

$basis = AssetVersion::fromMtimes($dirs);
check($basis === base_convert((string) $alt, 10, 36),
      'Kennung ist die jüngste mtime, base36-kodiert');

// Der eigentliche Befund: eine Änderung HINTER dem Symlink muss zählen.
$neu = $alt + 3600;
touch("$extern/shared.css", $neu);
// Cache umgehen: derselbe Verzeichnis-Satz wäre statisch gemerkt (in einer
// echten Anfrage gewollt — hier nicht).
$dirs2 = ["$web/css/", "$web/js"];
$nachher = AssetVersion::fromMtimes($dirs2);
check($nachher !== $basis, 'Änderung hinter dem Symlink ändert die Kennung');
check($nachher === base_convert((string) $neu, 10, 36),
      'und zwar auf genau deren mtime — der Symlink wird also betreten');

// Nicht-Assets dürfen nicht mitzählen.
touch("$extern/nicht-relevant.txt", $neu + 99999);
check(AssetVersion::fromMtimes(["$web/css//", "$web/js"]) === base_convert((string) $neu, 10, 36),
      'eine .txt-Datei beeinflusst die Kennung nicht');

// Robustheit: fehlende Verzeichnisse überspringen, nie werfen.
check(AssetVersion::fromMtimes(["$wurzel/gibt-es-nicht"]) === '0',
      'nur fehlende Verzeichnisse -> stabiles "0" statt Fehler');
check(AssetVersion::fromMtimes(["$wurzel/gibt-es-nicht", "$web/js/"]) === base_convert((string) $alt, 10, 36),
      'ein fehlendes Verzeichnis neben einem vorhandenen wird übersprungen');

// Ergebnis-Cache: zweiter Aufruf mit identischem Satz liefert dasselbe.
$c1 = AssetVersion::fromMtimes($dirs);
$c2 = AssetVersion::fromMtimes($dirs);
check($c1 === $c2, 'Ergebnis wird pro Verzeichnis-Satz gemerkt');

// ── forFile ─────────────────────────────────────────────────────────────
check(AssetVersion::forFile($web, 'css/app.css') === '?v=' . base_convert((string) $alt, 10, 36),
      'forFile liefert ?v= mit der mtime der Datei');
check(AssetVersion::forFile($web, '/css/app.css') === AssetVersion::forFile($web, 'css/app.css'),
      'forFile: führender Schrägstrich ist gleichwertig');
check(AssetVersion::forFile($web, 'css/shared/shared.css') === '?v=' . base_convert((string) $neu, 10, 36),
      'forFile folgt dem Symlink (filemtime tut das von selbst)');
check(AssetVersion::forFile($web, 'css/gibt-es-nicht.css') === '',
      'fehlende Datei -> leerer String, keine kaputte URL mit ?v=');

// ── Aufräumen ───────────────────────────────────────────────────────────
unlink("$web/css/shared");
foreach (["$web/css/app.css", "$web/js/app.js", "$extern/shared.css", "$extern/nicht-relevant.txt"] as $f) {
    @unlink($f);
}
foreach (["$web/css", "$web/js", $web, "$wurzel/app", $extern, $wurzel] as $d) {
    @rmdir($d);
}

echo "\n" . ($fehler === 0 ? "✓ $ok/$ok bestanden.\n" : "✗ $fehler von " . ($ok + $fehler) . " fehlgeschlagen.\n");
exit($fehler === 0 ? 0 : 1);
