<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Shared app footer — Rule §13 (ui-design-rules.md).
 *
 * Three-column grid: Impressum link left, © owner center, version string right.
 * Each app must define APP_VERSION, APP_BUILD, APP_ENV constants (or pass them
 * explicitly) so the footer can render "Major.Minor.Build APP_ENV".
 *
 * Usage:
 *   Footer::render(['base' => $base]);
 *
 *   // or explicit:
 *   Footer::render([
 *       'base'          => $base,
 *       'impressumHref' => $base . '/impressum.html',
 *       'owner'         => 'Erik R. Huemer',
 *       'version'       => '1.2.3 prod',
 *   ]);
 */
final class Footer
{
    public static function render(array $a = []): void
    {
        $base  = rtrim((string) ($a['base'] ?? ''), '/');
        $owner = (string) ($a['owner'] ?? 'Erik R. Huemer');

        $impressumHref = $a['impressumHref'] ?? ($base . '/impressum.html');

        $version = $a['version'] ?? null;
        if ($version === null) {
            $v = defined('APP_VERSION') ? (string) APP_VERSION : '0.0';
            $b = defined('APP_BUILD')   ? (string) APP_BUILD   : '0';
            $e = defined('APP_ENV')     ? (string) APP_ENV     : '';
            $version = trim($v . '.' . $b . ($e !== '' ? ' ' . $e : ''));
        }

        $year = (string) ($a['year'] ?? date('Y'));

        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        echo '<footer class="app-footer">';
        echo '<a href="' . $h((string) $impressumHref) . '">Impressum</a>';
        echo '<span>&copy; ' . $h($year) . ' ' . $h($owner) . '</span>';
        echo '<span>' . $h((string) $version) . '</span>';
        echo '</footer>';
    }
}
