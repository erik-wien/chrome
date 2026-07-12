<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Shared app footer — Rule §13 (ui-design-rules.md).
 *
 * Three-column grid: Impressum link left, © owner center, version string right.
 * Each app must define APP_VERSION, APP_BUILD, APP_ENV constants (or pass them
 * explicitly) so the footer can render "Major.Minor.Build STAGE" per Rule §13.
 *
 * STAGE is derived from APP_ENV:
 *   'local' (or anything matching dev targets) → DEV
 *   everything else → PROD
 * Callers can override by passing 'stage' => 'DEV' | 'PROD'.
 *
 * Usage:
 *   Footer::render(['base' => $base]);
 *
 *   // or explicit:
 *   Footer::render([
 *       'base'          => $base,
 *       'impressumHref' => $base . '/impressum.php',
 *       'owner'         => 'Erik R. Accart-Huemer',
 *       'version'       => '1.2.3 PROD',
 *   ]);
 */
final class Footer
{
    public static function render(array $a = []): void
    {
        $base  = rtrim((string) ($a['base'] ?? ''), '/');
        $owner = (string) ($a['owner'] ?? 'Erik R. Accart-Huemer');

        $impressumHref = $a['impressumHref'] ?? ($base . '/impressum.php');

        $version = $a['version'] ?? null;
        if ($version === null) {
            $v = defined('APP_VERSION') ? (string) APP_VERSION : '0.0';
            $b = defined('APP_BUILD')   ? (string) APP_BUILD   : '0';
            $stage = (string) ($a['stage'] ?? self::deriveStage($a['devTargets'] ?? null));
            $version = trim($v . '.' . $b . ($stage !== '' ? ' ' . $stage : ''));
        }

        $year = (string) ($a['year'] ?? date('Y'));

        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        echo '<footer class="app-footer">';
        echo '<a href="' . $h((string) $impressumHref) . '">Impressum</a>';
        echo '<span>&copy; ' . $h($year) . ' ' . $h($owner) . '</span>';
        echo '<span>' . $h((string) $version) . '</span>';
        echo '</footer>';

        // Shared form enhancement (password-reveal eye + clear-×) on every page.
        // Needs the CSP nonce — falls back to the global $_cspNonce set by
        // erikr/auth bootstrap() in every app (no per-app call-site change needed).
        $nonce = (string) ($a['cspNonce'] ?? ($GLOBALS['_cspNonce'] ?? ''));
        if ($nonce !== '') {
            echo '<script src="' . $h($base) . '/css/shared/js/field-enhance.js" nonce="'
               . $h($nonce) . '"></script>';
        }
    }

    /**
     * Derive STAGE from APP_ENV per Rule §13: dev/test targets → DEV, real
     * production deploy targets (world4you) → PROD.
     *
     * `akadbrain` is a DEV2 test tier (*.eriks.cloud) and counts as DEV. Apps
     * may override the whole list via the `devTargets` render option; when
     * omitted the library default below applies.
     *
     * @param list<string>|null $devTargets Override for the DEV target list.
     */
    private static function deriveStage(?array $devTargets = null): string
    {
        if (!defined('APP_ENV')) {
            return '';
        }
        $env = strtolower((string) APP_ENV);
        $devTargets ??= ['local', 'localhost', 'dev', 'development', 'staging', 'akadbrain'];
        return in_array($env, $devTargets, true) ? 'DEV' : 'PROD';
    }
}
