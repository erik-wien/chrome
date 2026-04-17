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
 *       'owner'         => 'Erik R. Huemer',
 *       'version'       => '1.2.3 PROD',
 *   ]);
 */
final class Footer
{
    public static function render(array $a = []): void
    {
        $base  = rtrim((string) ($a['base'] ?? ''), '/');
        $owner = (string) ($a['owner'] ?? 'Erik R. Huemer');

        $impressumHref = $a['impressumHref'] ?? ($base . '/impressum.php');

        $version = $a['version'] ?? null;
        if ($version === null) {
            $v = defined('APP_VERSION') ? (string) APP_VERSION : '0.0';
            $b = defined('APP_BUILD')   ? (string) APP_BUILD   : '0';
            $stage = (string) ($a['stage'] ?? self::deriveStage());
            $version = trim($v . '.' . $b . ($stage !== '' ? ' ' . $stage : ''));
        }

        $year = (string) ($a['year'] ?? date('Y'));

        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        echo '<footer class="app-footer">';
        echo '<a href="' . $h((string) $impressumHref) . '">Impressum</a>';
        echo '<span>&copy; ' . $h($year) . ' ' . $h($owner) . '</span>';
        echo '<span>' . $h((string) $version) . '</span>';
        echo '</footer>';
    }

    /**
     * Derive STAGE from APP_ENV per Rule §13: local dev targets → DEV, everything
     * else (production deploy targets like akadbrain, world4you) → PROD.
     */
    private static function deriveStage(): string
    {
        if (!defined('APP_ENV')) {
            return '';
        }
        $env = strtolower((string) APP_ENV);
        $devTargets = ['local', 'localhost', 'dev', 'development', 'staging'];
        return in_array($env, $devTargets, true) ? 'DEV' : 'PROD';
    }
}
