<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Suite-Policy §5 / Design-Spec 2026-07-24 — shared status-page module.
 *
 * Every app defines a list of "checks" (name + callable) and hands them to
 * Status::run(). The result is cached (~60 s, file-backed) so page loads
 * don't hammer external APIs/DBs. Status::render() draws the HTML page
 * (traffic-light dots, no emojis — Rule §11); Status::json() serves the
 * `?format=json` variant for dashboard aggregation.
 *
 * Filtering contract: run() executes ALL checks (including adminOnly ones)
 * and writes ONE shared cache file — Admin- and User-Requests read the same
 * cache. adminOnly filtering happens downstream, in render() and json(),
 * never in run(). This means an adminOnly check's callable *does* run for a
 * plain user's request that happens to miss the cache — it is simply not
 * shown/exported to that user afterwards.
 *
 * No auth in this module — the app's status.php is responsible for
 * auth_require()/token checks before calling render()/json(). Example
 * app-side status.php:
 *
 *   require __DIR__ . '/../inc/initialize.php';
 *   auth_require();
 *
 *   $checks = [
 *       ['name' => 'Datenbank', 'check' => function () use ($con) {
 *           return Status::dbCheck(fn() => $con->query('SELECT 1') !== false);
 *       }],
 *       ['name' => 'Externe API', 'check' => function () {
 *           return Status::httpCheck('https://example.com/health');
 *       }],
 *       ['name' => 'Nginx-Log', 'adminOnly' => true, 'check' => function () {
 *           return is_readable('/var/log/nginx/access.log')
 *               ? ['state' => 'ok']
 *               : ['state' => 'fail', 'detail' => 'nicht lesbar'];
 *       }],
 *   ];
 *
 *   $results = Status::run($checks, [
 *       'cacheFile' => __DIR__ . '/../data/status_cache.json',
 *       'cacheTtl'  => 60,
 *   ]);
 *
 *   $statusToken  = (string) ($config['status_token'] ?? '');
 *   $isTokenAuth  = $statusToken !== '' && isset($_GET['status_token'])
 *       && hash_equals($statusToken, (string) $_GET['status_token']);
 *
 *   if (($_GET['format'] ?? '') === 'json') {
 *       if (empty($_SESSION['loggedin']) && !$isTokenAuth) {
 *           http_response_code(403);
 *           exit;
 *       }
 *       header('Content-Type: application/json');
 *       echo Status::json($results, ['app' => 'energie']);
 *       exit;
 *   }
 *
 *   $isAdmin = (($_SESSION['rights'] ?? '') === 'Admin');
 *   // … app layout / Header::render() …
 *   Status::render($results, $isAdmin, ['cspNonce' => $_cspNonce]);
 *
 * CSS classes emitted by render() (also documented here for app.css authors
 * who want to restyle instead of relying on the inline block):
 * `.status-list`, `.status-item`, `.status-dot` (+ `--ok`/`--warn`/`--fail`),
 * `.status-name`, `.status-meta`, `.status-detail`, `.status-generated`.
 */
final class Status
{
    private const STATES = ['ok', 'warn', 'fail'];

    /**
     * Run every check (try/catch per check, timed), optionally via a shared
     * file cache. Returns ['generated_ts' => int, 'checks' => [ [name,
     * state, detail, last_success_ts, duration_ms, adminOnly], … ] ].
     *
     * $opts:
     *   - cacheFile: string|null — path to a JSON cache file (recommend a
     *     path inside the app's data/ dir). null/omitted = no caching.
     *   - cacheTtl:  int — seconds a cache file stays valid. Default 60.
     */
    public static function run(array $checks, array $opts = []): array
    {
        $cacheFile = $opts['cacheFile'] ?? null;
        $cacheTtl  = (int) ($opts['cacheTtl'] ?? 60);

        if (is_string($cacheFile) && $cacheFile !== '') {
            $cached = self::readCache($cacheFile, $cacheTtl);
            if ($cached !== null) {
                return $cached;
            }
        }

        $out = [];
        foreach ($checks as $def) {
            $name      = (string) ($def['name'] ?? '');
            $adminOnly = (bool) ($def['adminOnly'] ?? false);
            $callable  = $def['check'] ?? null;

            $t0 = microtime(true);
            try {
                $r = is_callable($callable) ? $callable() : [];
                if (!is_array($r)) {
                    $r = [];
                }
            } catch (\Throwable $ex) {
                $r = ['state' => 'fail', 'detail' => $ex->getMessage()];
            }
            $durationMs = (int) round((microtime(true) - $t0) * 1000);

            $state = (string) ($r['state'] ?? 'fail');
            if (!in_array($state, self::STATES, true)) {
                $state = 'fail';
            }

            $out[] = [
                'name'            => $name,
                'state'           => $state,
                'detail'          => isset($r['detail']) ? (string) $r['detail'] : null,
                'last_success_ts' => isset($r['last_success_ts']) && $r['last_success_ts'] !== null
                    ? (int) $r['last_success_ts']
                    : null,
                'duration_ms'     => $durationMs,
                'adminOnly'       => $adminOnly,
            ];
        }

        $result = [
            'generated_ts' => time(),
            'checks'       => $out,
        ];

        if (is_string($cacheFile) && $cacheFile !== '') {
            self::writeCache($cacheFile, $result);
        }

        return $result;
    }

    /**
     * Render the status page body (no <!DOCTYPE>/<head>/<Header::render> —
     * the app embeds this in its own layout, same convention as Header/Footer).
     *
     * $results is the array returned by run(). adminOnly checks are omitted
     * entirely for non-admins (name + dot never shown); `detail` is emitted
     * only when $isAdmin.
     *
     * $opts:
     *   - cspNonce: string — nonce for the emitted <style> block.
     *   - cacheTtl: int — only used for the "Cache max. …" hint text; pass
     *     the same value given to run(). Default 60.
     */
    public static function render(array $results, bool $isAdmin, array $opts = []): void
    {
        $nonce    = (string) ($opts['cspNonce'] ?? '');
        $cacheTtl = (int) ($opts['cacheTtl'] ?? 60);

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

        echo '<style' . $nonceAttr . '>';
        echo '.status-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem}';
        echo '.status-item{display:flex;flex-wrap:wrap;align-items:baseline;gap:.5rem .75rem;'
           . 'padding:.6rem .8rem;border:1px solid var(--color-border,#ddd);border-radius:var(--radius,6px)}';
        echo '.status-dot{display:inline-block;width:.7rem;height:.7rem;border-radius:50%;flex:0 0 auto}';
        echo '.status-dot--ok{background:var(--color-success,#2e7d32)}';
        echo '.status-dot--warn{background:var(--color-warning,#f9a825)}';
        echo '.status-dot--fail{background:var(--color-danger,#c62828)}';
        echo '.status-name{font-weight:600}';
        echo '.status-meta{color:var(--color-text-muted,#666);font-size:.9em}';
        echo '.status-detail{flex-basis:100%;color:var(--color-text-muted,#666);'
           . 'font-size:.85em;white-space:pre-wrap}';
        echo '.status-generated{color:var(--color-text-muted,#666);font-size:.85em;margin-top:.75rem}';
        echo '</style>';

        $generatedTs = (int) ($results['generated_ts'] ?? time());
        $checks      = (array) ($results['checks'] ?? []);

        echo '<ul class="status-list">';
        foreach ($checks as $c) {
            $adminOnly = (bool) ($c['adminOnly'] ?? false);
            if ($adminOnly && !$isAdmin) {
                continue;
            }

            $state = (string) ($c['state'] ?? 'fail');
            if (!in_array($state, self::STATES, true)) {
                $state = 'fail';
            }
            $name = (string) ($c['name'] ?? '');

            echo '<li class="status-item">';
            echo '<span class="status-dot status-dot--' . $e($state) . '" aria-hidden="true"></span>';
            echo '<span class="status-name">' . $e($name) . '</span>';

            $lastTs = $c['last_success_ts'] ?? null;
            if ($lastTs !== null) {
                echo '<span class="status-meta">zuletzt ok: ' . $e(date('d.m.Y H:i', (int) $lastTs)) . '</span>';
            }

            if ($isAdmin && !empty($c['detail'])) {
                echo '<span class="status-detail">' . $e((string) $c['detail']) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';

        echo '<p class="status-generated">Stand: ' . $e(date('d.m.Y H:i:s', $generatedTs))
           . ' (Cache max. ' . $cacheTtl . ' s)</p>';
    }

    /**
     * `{app, generated_ts, checks: [{name, state, last_success_ts}]}` —
     * never contains `detail` (Interna) and never contains adminOnly checks,
     * regardless of caller (session- or token-authenticated alike — the app
     * decides who may reach this endpoint at all before calling json()).
     *
     * $opts['app'] — app key, e.g. 'energie'.
     */
    public static function json(array $results, array $opts = []): string
    {
        $app         = (string) ($opts['app'] ?? '');
        $generatedTs = (int) ($results['generated_ts'] ?? time());
        $checks      = (array) ($results['checks'] ?? []);

        $out = [];
        foreach ($checks as $c) {
            if (!empty($c['adminOnly'])) {
                continue;
            }
            $state = (string) ($c['state'] ?? 'fail');
            if (!in_array($state, self::STATES, true)) {
                $state = 'fail';
            }
            $out[] = [
                'name'            => (string) ($c['name'] ?? ''),
                'state'           => $state,
                'last_success_ts' => isset($c['last_success_ts']) && $c['last_success_ts'] !== null
                    ? (int) $c['last_success_ts']
                    : null,
            ];
        }

        $json = json_encode([
            'app'          => $app,
            'generated_ts' => $generatedTs,
            'checks'       => $out,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{"app":"","generated_ts":0,"checks":[]}' : $json;
    }

    /**
     * Convenience check for external HTTP endpoints. §21: HTTP >= 400 (or a
     * curl-level failure) is always a fail — never silently treated as "not
     * found"/ok. Uses a short timeout so a slow/dead endpoint can't stall
     * the whole status page.
     */
    public static function httpCheck(string $url, int $timeoutSec = 3, array $curlOpts = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['state' => 'fail', 'detail' => 'curl_init fehlgeschlagen'];
        }
        curl_setopt_array($ch, $curlOpts + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno !== 0) {
            return ['state' => 'fail', 'detail' => $error !== '' ? $error : ('curl-Fehler #' . $errno)];
        }
        if ($code >= 400 || $code === 0) {
            return ['state' => 'fail', 'detail' => 'HTTP ' . $code];
        }
        return ['state' => 'ok', 'detail' => 'HTTP ' . $code];
    }

    /**
     * Thin try/catch wrapper around an app-supplied DB ping callable. The
     * callable should throw on failure (or return `false`); anything else
     * (incl. void/true) counts as ok.
     */
    public static function dbCheck(callable $ping, string $okDetail = ''): array
    {
        try {
            $result = $ping();
        } catch (\Throwable $ex) {
            return ['state' => 'fail', 'detail' => $ex->getMessage()];
        }
        if ($result === false) {
            return ['state' => 'fail', 'detail' => 'Ping fehlgeschlagen'];
        }
        return ['state' => 'ok', 'detail' => $okDetail];
    }

    /**
     * Reads the cache file if present and within $ttl seconds. Validity is
     * judged by the `generated_ts` stored *inside* the JSON (not the file's
     * mtime) — mtime reflects when the file was last written/touched, not
     * when the checks actually ran, and can drift from it (e.g. a rewrite of
     * identical content, or filesystem timestamp quirks).
     * Returns null (=> caller must re-run checks) on any miss/invalid content.
     */
    private static function readCache(string $file, int $ttl): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['checks'], $data['generated_ts']) || !is_array($data['checks'])) {
            return null;
        }
        if (!is_int($data['generated_ts']) || (time() - $data['generated_ts']) > $ttl) {
            return null;
        }
        return $data;
    }

    /**
     * Atomic write (tmp file + rename) so concurrent readers never observe a
     * partially-written cache file. A stampede of parallel misses (several
     * requests running the checks simultaneously before the first write
     * lands) is possible but bounded — each check has its own short timeout
     * — and the last writer simply wins; no locking is used.
     */
    private static function writeCache(string $file, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json) === false) {
            return;
        }
        @rename($tmp, $file);
    }
}
