<?php

declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Erikr\Chrome\AppsMenu — single source of truth for the cross-app navigation
 * ("Apps" dropdown) shared by every app in the Jardyx/eriks.cloud suite.
 *
 * Before this class each app hand-maintained its own divergent list (7 copies
 * that disagreed on hosts, labels, ordering and which apps were even present —
 * see mcp/docs/2026-07-12-suite-konsistenz-audit.md §2). Now every app calls
 * AppsMenu::build('<selfkey>', APP_ENV) and passes the result verbatim as the
 * Header::render() `appsMenu` option.
 *
 * The prod hosts are all *.jardyx.com (the confirmed SSO-return targets, audit
 * S2). The self app is excluded from the prod list. Suite-Policy §1 forbids
 * dev/test links in the Apps menu (TASK-6) — the menu only ever carries the
 * jardyx.com production links, in any env.
 */
final class AppsMenu
{
    /**
     * Canonical suite registry. Key = stable app identifier passed as
     * $currentKey; order here is the rendered order. `prod` is the absolute
     * jardyx.com URL, `test` the local *.test URL.
     *
     * @var array<string, array{label: string, prod: string, test: string}>
     */
    private const APPS = [
        'energie'   => ['label' => 'Energie',    'prod' => 'https://energie.jardyx.com',   'test' => 'http://energie.test'],
        'wlmonitor' => ['label' => 'WL Monitor', 'prod' => 'https://wlmonitor.jardyx.com', 'test' => 'http://wlmonitor.test'],
        'zeit'      => ['label' => 'Zeit',       'prod' => 'https://zeit.jardyx.com',      'test' => 'http://zeit.test'],
        'chat'      => ['label' => 'Chat',       'prod' => 'https://chat.jardyx.com',      'test' => 'http://chat.test'],
        'suche'     => ['label' => 'Suche',      'prod' => 'https://www.jardyx.com',       'test' => 'http://suche.test'],
        'lastfm'    => ['label' => 'Last.fm',    'prod' => 'https://lastfm.jardyx.com',    'test' => 'http://lastfm.test'],
        'biblio'    => ['label' => 'Biblio',     'prod' => 'https://biblio.jardyx.com',    'test' => 'http://biblio.test'],
    ];

    /**
     * Build the `appsMenu` array for Header::render().
     *
     * @param string      $currentKey Registry key of the current app (excluded from the menu).
     * @param string|null $env        Deprecated (TASK-6) — no longer used; the Test submenu it
     *                                used to gate on 'local' was removed per Suite-Policy §1.
     *                                Kept for call-site compatibility (AppsMenu::build(key, APP_ENV)).
     * @return list<array<string, mixed>> Header-compatible menu entries.
     */
    public static function build(string $currentKey, ?string $env = null): array
    {
        if (!isset(self::APPS[$currentKey])) {
            throw new \InvalidArgumentException("AppsMenu: unknown app key '{$currentKey}'");
        }

        $menu = [];
        foreach (self::APPS as $key => $app) {
            if ($key === $currentKey) {
                continue;
            }
            $menu[] = ['href' => $app['prod'], 'label' => $app['label']];
        }

        return $menu;
    }
}
