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
     * production URL.
     *
     * `hosts` listet ALLE Hostnamen, unter denen die App laeuft. Das Feld ist
     * seit 2026-09-05 die einzige Quelle fuer suches SSO-Rueckweg-Allowlist
     * (s. ssoHosts()) -- vorher stand dieselbe Information dort ein zweites
     * Mal von Hand, und genau das war der Grund, warum man nach dem Login
     * "bei allen Apps immer wieder" auf der Startseite landete: eine neue App
     * eintragen MUSS man hier (sonst wirft build() sofort), die Allowlist
     * drueben aber vergisst man, weil dort nichts kracht -- der Rueckweg wird
     * nur still verworfen.
     *
     * ACHTUNG: Damit ist dieses Feld sicherheitsrelevant. Ein Host hier
     * erlaubt eine Weiterleitung nach dem Login. Nur echte Suite-Hosts
     * eintragen, niemals Wildcards.
     *
     * @var array<string, array{label: string, prod: string, hosts: list<string>}>
     */
    private const APPS = [
        'energie'   => ['label' => 'Energie',    'prod' => 'https://energie.jardyx.com',
                        'hosts' => ['energie.jardyx.com', 'energie.eriks.cloud', 'energie.test']],
        'wlmonitor' => ['label' => 'WL Monitor', 'prod' => 'https://wlmonitor.jardyx.com',
                        'hosts' => ['wlmonitor.jardyx.com', 'wlmonitor.eriks.cloud', 'wlmonitor.test']],
        'zeit'      => ['label' => 'Zeit',       'prod' => 'https://zeit.jardyx.com',
                        'hosts' => ['zeit.jardyx.com', 'werda.eriks.cloud', 'zeit.test', 'werda.test']],
        'chat'      => ['label' => 'Chat',       'prod' => 'https://chat.jardyx.com',
                        'hosts' => ['chat.jardyx.com', 'chat.eriks.cloud', 'chat.test']],
        // suche IST der zentrale Login. eriks.cloud, www.eriks.cloud und
        // suche.eriks.cloud sind laut nginx auf akadbrain DERSELBE vhost.
        'suche'     => ['label' => 'Suche',      'prod' => 'https://www.jardyx.com',
                        'hosts' => ['www.jardyx.com', 'eriks.cloud', 'www.eriks.cloud',
                                    'suche.eriks.cloud', 'suche.test']],
        'lastfm'    => ['label' => 'Last.fm',    'prod' => 'https://lastfm.jardyx.com',
                        'hosts' => ['lastfm.jardyx.com', 'lastfm.eriks.cloud', 'lastfm.test']],
        'biblio'    => ['label' => 'Biblio',     'prod' => 'https://biblio.jardyx.com',
                        'hosts' => ['biblio.jardyx.com', 'biblio.eriks.cloud', 'biblio.test']],
        // mailprint lebt nur auf akadbrain/eriks.cloud (kein jardyx.com) — bewusste
        // Abweichung vom jardyx-Muster: die prod-URL zeigt auf eriks.cloud.
        'mailprint' => ['label' => 'Mail Print', 'prod' => 'https://mailprint.eriks.cloud',
                        'hosts' => ['mailprint.eriks.cloud', 'mailprint.test']],
        // display ebenso nur auf eriks.cloud: dort haengt das einzige E1003-Geraet,
        // und nur dort laeuft der MQTT-Broker. Herausgeloest aus wlmonitor
        // 2026-09-05 — vorher lag der Board-Code dort und wurde nach jardyx.com
        // mitdeployt, wo er per BOARD_FEATURE_AVAILABLE wieder versteckt werden
        // musste. Als eigene App wird er dorthin gar nicht erst ausgerollt.
        'display'   => ['label' => 'Display',    'prod' => 'https://display.eriks.cloud',
                        'hosts' => ['display.eriks.cloud', 'display.test']],
    ];

    /**
     * Alle Hostnamen der Suite — die einzige Quelle fuer suches
     * SSO-Rueckweg-Allowlist (AUTH_SSO_ALLOWED_HOSTS).
     *
     * Warum abgeleitet statt danebengestellt: Bis 2026-09-05 stand dieselbe
     * Information zweimal da. Eine App HIER einzutragen kann man nicht
     * vergessen -- build() wirft sonst sofort "unknown app key", und keine
     * Seite der App rendert. Die Allowlist drueben vergisst man dagegen
     * immer, weil dort nichts kracht: sso_validate_return() verwirft den
     * Rueckweg still, der Nutzer landet auf der Startseite und niemand
     * erfaehrt warum. Genau dieser Fehler trat "bei allen Apps immer wieder"
     * auf, zuletzt bei display.eriks.cloud.
     *
     * Die Liste enthaelt bewusst KEINE Wildcards und wird nicht aus der
     * Umgebung ergaenzt: sie ist eine Sicherheitsgrenze gegen offene
     * Weiterleitungen, kein Komfort-Mechanismus.
     *
     * @return list<string> Hostnamen ohne Schema und ohne Port, ohne Dubletten.
     */
    public static function ssoHosts(): array
    {
        $hosts = [];
        foreach (self::APPS as $app) {
            foreach ($app['hosts'] ?? [] as $host) {
                $hosts[$host] = true;
            }
        }
        return array_keys($hosts);
    }

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
