<?php

declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Cache-Buster aus den Dateizeiten der ausgelieferten Assets.
 *
 * Zweck: eine Kennung, die sich von selbst ändert, sobald irgendein JS/CSS
 * angefasst wurde — auch wenn die Änderung im geteilten `css_library` liegt und
 * nicht in der App. Handgepflegte Build-Nummern erfüllen das nicht: sie werden
 * vergessen, und dann liefert der Server neues PHP an einen Browser aus, der
 * noch das alte Skript im Cache hat.
 *
 * Warum das eine Rolle spielt (css_library TASK-12): die Apps binden
 * `css/shared/` per Symlink ein und werden mit `rsync --copy-links` ausgerollt,
 * das die mtime erhält. Eine Änderung an der Library ist dadurch auf dem Server
 * an der Dateizeit erkennbar — man muss nur hinsehen.
 */
final class AssetVersion
{
    /** @var array<string,string> Ergebnis-Cache pro Verzeichnis-Satz. */
    private static array $cache = [];

    /**
     * Jüngste mtime aller *.js/*.css unter $dirs, kompakt als base36.
     *
     * FOLLOW_SYMLINKS ist der entscheidende Teil: ohne das Flag betritt
     * RecursiveDirectoryIterator symlinkte Verzeichnisse nicht, und genau das
     * ist `web/css/shared` in der Entwicklung. Ohne Flag sah der Scan in biblio
     * 5 von 19 Dateien und übersah die Änderung an der geteilten `admin.js`
     * komplett (nachgemessen 2026-07-30). Auf dem Server fällt es nicht auf,
     * weil dort echte Dateien liegen — der Fehler wäre also nur in der
     * Entwicklung sichtbar geworden, wo man ihn am ehesten für einen
     * Browser-Cache hält.
     *
     * Fehlende Verzeichnisse werden übersprungen; findet sich nichts, ist das
     * Ergebnis '0' (stabil, damit die URL gültig bleibt).
     *
     * @param list<string> $dirs
     */
    public static function fromMtimes(array $dirs): string
    {
        $key = implode('|', $dirs);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $neueste = 0;
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $dir,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
                // Ein unlesbares Unterverzeichnis darf keinen Fatal auslösen:
                // die Kennung ist Beiwerk, die Seite muss trotzdem laden.
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($it as $f) {
                $ext = strtolower($f->getExtension());
                if ($ext !== 'js' && $ext !== 'css') {
                    continue;
                }
                $m = $f->getMTime();
                if ($m > $neueste) {
                    $neueste = $m;
                }
            }
        }

        return self::$cache[$key] = $neueste > 0 ? base_convert((string) $neueste, 10, 36) : '0';
    }

    /**
     * Buster für eine EINZELNE Datei, relativ zum Web-Verzeichnis, inklusive
     * '?v=' — bzw. '' wenn die Datei fehlt.
     *
     * Für Seiten, die nur ein oder zwei Assets einbinden: ein `filemtime()`
     * statt eines Verzeichnis-Scans. `filemtime()` folgt Symlinks von selbst,
     * hier braucht es also kein Flag.
     */
    public static function forFile(string $webRoot, string $rel): string
    {
        $m = @filemtime(rtrim($webRoot, '/') . '/' . ltrim($rel, '/'));
        return $m ? '?v=' . base_convert((string) $m, 10, 36) : '';
    }
}
