<?php
declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Shared app header — Rule §12 (ui-design-rules.md).
 *
 * Emits the fixed top bar: brand on the left, optional AppMenu + user dropdown
 * on the right. Does NOT emit <!DOCTYPE>/<head> — each app keeps its own.
 *
 * Usage:
 *   Header::render([
 *       'appName'     => 'Energie',
 *       'base'        => $base,
 *       'cspNonce'    => $_cspNonce,
 *       'csrfToken'   => csrf_token(),
 *       'pageType'    => 'daily',
 *       'appMenu'     => [
 *           ['href' => 'daily.php?date=…',  'label' => 'Aktuell', 'type' => 'daily'],
 *           // Nested dropdown group (no 'href'; 'children' = sub-items):
 *           ['label' => 'More', 'children' => [
 *               ['href' => 'http://app.test', 'label' => 'App'],
 *           ]],
 *       ],
 *       'extraItems'  => [],   // raw HTML snippets rendered before theme row
 *       'leftExtra'   => '',   // raw HTML snippet rendered inside .header-left
 *                              // after the brand (e.g. a search box)
 *       'spritePath'  => null, // absolute path to an SVG sprite file; if set,
 *                              // readfile()d immediately after <body> so
 *                              // <use href="#icon-…"> resolves inline
 *   ]);
 */
final class Header
{
    public static function render(array $a): void
    {
        $appName  = (string) ($a['appName']  ?? '');
        $base     = rtrim((string) ($a['base'] ?? ''), '/');
        $nonce    = (string) ($a['cspNonce']  ?? '');
        $csrf     = (string) ($a['csrfToken'] ?? '');
        $pageType = (string) ($a['pageType']  ?? '');
        $appMenu    = (array)  ($a['appMenu']   ?? []);
        $extras     = (array)  ($a['extraItems'] ?? []);
        $leftExtra  = (string) ($a['leftExtra']  ?? '');
        $spritePath = $a['spritePath'] ?? null;

        // Session-derived state (apps may override)
        $loggedIn = array_key_exists('loggedIn', $a)
            ? (bool) $a['loggedIn']
            : !empty($_SESSION['loggedin']);
        $username = (string) ($a['username'] ?? ($_SESSION['username'] ?? ''));
        $isAdmin  = array_key_exists('isAdmin', $a)
            ? (bool) $a['isAdmin']
            : (($_SESSION['rights'] ?? '') === 'Admin');
        $theme    = (string) ($a['theme'] ?? ($_SESSION['theme'] ?? 'auto'));
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) { $theme = 'auto'; }

        // URL defaults — every app can override, but defaults follow the §12 layout
        $brandHref     = $a['brandHref']     ?? ($base . '/');
        $brandLogoSrc  = $a['brandLogoSrc']  ?? ($base . '/assets/jardyx.svg');
        $avatarSrc     = $a['avatarSrc']     ?? ($base . '/avatar.php');
        $prefsHref     = $a['prefsHref']     ?? ($base . '/preferences.php');
        $securityHref  = $a['securityHref']  ?? ($base . '/security.php');
        $adminHref     = $a['adminHref']     ?? ($base . '/admin.php');
        $helpHref      = array_key_exists('helpHref', $a) ? $a['helpHref'] : ($base . '/help.php');
        $logoutHref    = $a['logoutHref']    ?? ($base . '/logout.php');
        $themeEndpoint = $a['themeEndpoint'] ?? ($base . '/preferences.php');
        $anonLoginHref = array_key_exists('anonLoginHref', $a) ? $a['anonLoginHref'] : ($base . '/login.php');

        // forms.js path — defaults to the standard shared-symlink location;
        // pass formsJsPath => null to opt out, or an explicit path to override.
        $formsJsPath = array_key_exists('formsJsPath', $a)
            ? $a['formsJsPath']
            : ($base . '/css/shared/js/forms.js');

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

        // Theme-bootstrap script: apply stored theme before body paints
        echo '<script' . $nonceAttr . '>document.documentElement.dataset.theme = '
           . json_encode($theme) . ';</script>';

        // Inline SVG sprite — emitted once per page so <use href="#icon-…">
        // references resolve without an extra HTTP request.
        if ($spritePath !== null && is_string($spritePath) && is_file($spritePath)) {
            readfile($spritePath);
        }

        echo '<a class="skip-link" href="#main-content">Zum Inhalt springen</a>';
        echo '<header class="app-header">';

        // ── Left cluster ────────────────────────────────────────────────
        echo '<div class="header-left">';
        echo '<a class="brand" href="' . $e((string) $brandHref) . '">';
        echo '<img src="' . $e((string) $brandLogoSrc) . '" class="header-logo" '
           . 'width="28" height="28" alt="">';
        echo '<span class="header-appname">' . $e($appName) . '</span>';
        echo '</a>';
        if ($leftExtra !== '') {
            echo $leftExtra;
        }
        echo '</div>';

        // ── Right cluster ───────────────────────────────────────────────
        echo '<div class="header-right">';

        // AppMenu — optional, empty array = no menu
        if (!empty($appMenu)) {
            echo '<nav class="header-nav">';
            foreach ($appMenu as $item) {
                if (isset($item['children'])) {
                    if (!empty($item['adminOnly']) && !$isAdmin) continue;
                    $ddLabel = $e((string) ($item['label'] ?? ''));
                    echo '<div class="header-dropdown">';
                    echo '<button type="button" class="header-dropdown-trigger"'
                       . ' aria-haspopup="menu" aria-expanded="false">'
                       . $ddLabel
                       . '<svg aria-hidden="true" width="10" height="10" viewBox="0 0 10 10"'
                       . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'
                       . '<path d="M1 3l4 4 4-4"/></svg>'
                       . '</button>';
                    echo '<div class="header-dropdown-panel">';
                    foreach ((array) $item['children'] as $child) {
                        $childHref = (string) ($child['href'] ?? '#');
                        echo '<a href="' . $e($childHref) . '">' . $e((string) ($child['label'] ?? '')) . '</a>';
                    }
                    echo '</div></div>';
                } else {
                    $href  = (string) ($item['href']  ?? '#');
                    $label = (string) ($item['label'] ?? '');
                    $type  = (string) ($item['type']  ?? '');
                    if ($href !== '' && $href[0] !== '/' && !preg_match('~^[a-z]+://~i', $href) && $base !== '') {
                        $href = $base . '/' . ltrim($href, '/');
                    }
                    $activeAttr = ($type !== '' && $type === $pageType) ? ' class="active"' : '';
                    echo '<a href="' . $e($href) . '"' . $activeAttr . '>' . $e($label) . '</a>';
                }
            }
            echo '</nav>';
        }

        if ($loggedIn) {
            // ── User dropdown ───────────────────────────────────────────
            $un = $e($username);
            echo '<div class="user-menu">';
            echo '<button class="user-btn" type="button" '
               . 'aria-haspopup="menu" aria-expanded="false" aria-controls="user-dropdown" aria-label="Menü">';
            echo '<span>' . $un . '</span>';
            echo '<img src="' . $e((string) $avatarSrc) . '" class="avatar" '
               . 'width="26" height="26" alt="">';
            echo '<svg class="chevron" aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" '
               . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'
               . '<path d="M2 4l4 4 4-4"/></svg>';
            echo '</button>';

            // Dropdown panel — .dd-main + .dd-sub panels (mobile drill-down)
            $chevR = '<svg aria-hidden="true" width="10" height="10" viewBox="0 0 10 10" fill="none"'
                   . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                   . '<path d="M3 2l4 4-4 4"/></svg>';
            $chevL = '<svg aria-hidden="true" width="10" height="10" viewBox="0 0 10 10" fill="none"'
                   . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                   . '<path d="M7 2l-4 4 4 4"/></svg>';

            echo '<div class="user-dropdown" id="user-dropdown">';
            echo '<div class="dd-main">';

            // ── Nav section (mobile only — CSS hides at ≥768 px) ───────────
            if (!empty($appMenu)) {
                echo '<div class="dropdown-nav-section">';
                echo '<span class="dropdown-section-label">Apps</span>';
                foreach ($appMenu as $item) {
                    if (!isset($item['children'])) {
                        $href      = (string) ($item['href'] ?? '#');
                        $type      = (string) ($item['type'] ?? '');
                        $activeCls = ($type !== '' && $type === $pageType) ? ' active' : '';
                        echo '<a href="' . $e($href) . '" class="dropdown-link-btn' . $activeCls . '">'
                           . $e((string) ($item['label'] ?? '')) . '</a>';
                    } else {
                        if (!empty($item['adminOnly']) && !$isAdmin) continue;
                        $subId = 'dd-sub-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($item['label'] ?? '')));
                        echo '<button type="button" class="dd-trigger dd-chevron-btn dropdown-link-btn"'
                           . ' data-target="' . $e($subId) . '">'
                           . $e((string) ($item['label'] ?? '')) . $chevR . '</button>';
                    }
                }
                echo '</div>';
                echo '<div class="dropdown-divider"></div>';
            }

            // ── Account section ─────────────────────────────────────────────
            echo '<span class="dropdown-username">' . $un . '</span>';
            echo '<div class="dropdown-divider"></div>';
            // Mobile: single drill-down trigger into Konto sub-panel
            echo '<button type="button" class="dd-trigger dd-chevron-btn dd-mobile dropdown-link-btn"'
               . ' data-target="dd-sub-konto">Konto' . $chevR . '</button>';
            // Desktop: direct links
            echo '<a href="' . $e((string) $prefsHref) . '" class="dd-desktop dropdown-link-btn">Einstellungen</a>';
            echo '<a href="' . $e((string) $securityHref) . '" class="dd-desktop dropdown-link-btn">Passwort &amp; 2FA</a>';
            if ($isAdmin) {
                echo '<a href="' . $e((string) $adminHref) . '" class="dd-desktop dropdown-link-btn">Administration</a>';
            }
            if ($helpHref !== null) {
                echo '<a href="' . $e((string) $helpHref) . '" class="dropdown-link-btn">Hilfe</a>';
            }
            if (!empty($extras)) {
                echo '<div class="dropdown-divider"></div>';
                foreach ($extras as $snippet) { echo (string) $snippet; }
            }
            echo '<div class="dropdown-divider"></div>';
            echo '<div class="theme-row">';
            foreach (['light' => '☀', 'auto' => '⬤', 'dark' => '🌙'] as $val => $icon) {
                $active = ($theme === $val) ? ' active' : '';
                echo '<button class="theme-btn' . $active . '" data-theme="' . $val . '" '
                   . 'title="' . ($val === 'light' ? 'Hell' : ($val === 'dark' ? 'Dunkel' : 'Auto')) . '">'
                   . $icon . '</button>';
            }
            echo '</div>';
            echo '<div class="dropdown-divider"></div>';
            echo '<form method="post" action="' . $e((string) $logoutHref) . '" style="margin:0">';
            if ($csrf !== '') {
                echo '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '">';
            }
            echo '<button type="submit" class="dropdown-link-btn">Abmelden</button>';
            echo '</form>';
            echo '</div>'; // .dd-main

            // ── Sub-panels ──────────────────────────────────────────────────
            // App drill-downs (from appMenu items with children)
            foreach ($appMenu as $item) {
                if (!isset($item['children'])) continue;
                if (!empty($item['adminOnly']) && !$isAdmin) continue;
                $subId = 'dd-sub-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($item['label'] ?? '')));
                echo '<div class="dd-sub" id="' . $e($subId) . '">';
                echo '<button type="button" class="dd-back dropdown-link-btn">'
                   . $chevL . ' ' . $e((string) ($item['label'] ?? '')) . '</button>';
                foreach ((array) $item['children'] as $child) {
                    echo '<a href="' . $e((string) ($child['href'] ?? '#')) . '" class="dropdown-link-btn">'
                       . $e((string) ($child['label'] ?? '')) . '</a>';
                }
                echo '</div>';
            }
            // Konto sub-panel
            echo '<div class="dd-sub" id="dd-sub-konto">';
            echo '<button type="button" class="dd-back dropdown-link-btn">' . $chevL . ' Konto</button>';
            echo '<a href="' . $e((string) $prefsHref) . '" class="dropdown-link-btn">Einstellungen</a>';
            echo '<a href="' . $e((string) $securityHref) . '" class="dropdown-link-btn">Passwort &amp; 2FA</a>';
            if ($isAdmin) {
                echo '<a href="' . $e((string) $adminHref) . '" class="dropdown-link-btn">Administration</a>';
            }
            echo '</div>';

            echo '</div>'; // .user-dropdown
            echo '</div>'; // .user-menu
        } elseif ($anonLoginHref !== null) {
            echo '<a href="' . $e((string) $anonLoginHref) . '" class="user-btn" '
               . 'style="text-decoration:none">Anmelden</a>';
        }

        echo '</div>'; // .header-right
        echo '</header>';

        // ── forms.js (clear buttons for all eligible inputs) ────────────
        if (is_string($formsJsPath) && $formsJsPath !== '') {
            echo '<script defer src="' . $e($formsJsPath) . '"' . $nonceAttr . '></script>';
        }

        // ── Behaviour script: header nav dropdown ───────────────────────
        $hasNavDropdown = false;
        foreach ($appMenu as $item) {
            if (isset($item['children'])) { $hasNavDropdown = true; break; }
        }
        if ($hasNavDropdown) {
            echo '<script' . $nonceAttr . '>';
            echo '(function(){';
            echo 'var dds=document.querySelectorAll(".header-dropdown");';
            echo 'function closeAll(except){dds.forEach(function(d){if(d!==except){d.classList.remove("open");var t=d.querySelector(".header-dropdown-trigger");if(t)t.setAttribute("aria-expanded","false");}});}';
            echo 'dds.forEach(function(d){';
            echo 'var t=d.querySelector(".header-dropdown-trigger");if(!t)return;';
            echo 't.addEventListener("click",function(e){e.stopPropagation();var wasOpen=d.classList.contains("open");closeAll();if(!wasOpen){d.classList.add("open");t.setAttribute("aria-expanded","true");}else{t.setAttribute("aria-expanded","false");}});';
            echo '});';
            echo 'document.addEventListener("click",function(e){if(!e.target.closest(".header-dropdown")){closeAll();}});';
            echo 'document.addEventListener("keydown",function(e){if(e.key==="Escape")closeAll();});';
            echo '})();';
            echo '</script>';
        }

        // ── Behaviour script: dropdown toggle + theme switcher ──────────
        if ($loggedIn) {
            echo '<script' . $nonceAttr . '>';
            echo '(function(){';
            echo 'var menu=document.querySelector(".user-menu");if(!menu)return;';
            echo 'var btn=menu.querySelector(".user-btn");';
            echo 'btn.addEventListener("click",function(e){'
               . 'e.stopPropagation();menu.classList.toggle("open");'
               . 'btn.setAttribute("aria-expanded",menu.classList.contains("open")?"true":"false");});';
            echo 'function resetDd(){menu.querySelectorAll(".dd-sub").forEach(function(s){s.classList.remove("dd-open");});var m=menu.querySelector(".dd-main");if(m)m.classList.remove("dd-collapsed");}';
            echo 'document.addEventListener("click",function(e){'
               . 'if(e.target.closest(".user-menu"))return;'
               . 'menu.classList.remove("open");btn.setAttribute("aria-expanded","false");resetDd();});';
            echo 'menu.querySelectorAll(".dd-trigger").forEach(function(b){'
               . 'b.addEventListener("click",function(e){e.stopPropagation();'
               . 'var t=document.getElementById(b.dataset.target);var m=menu.querySelector(".dd-main");'
               . 'if(t)t.classList.add("dd-open");if(m)m.classList.add("dd-collapsed");});});';
            echo 'menu.querySelectorAll(".dd-back").forEach(function(b){'
               . 'b.addEventListener("click",function(e){e.stopPropagation();'
               . 'var s=b.closest(".dd-sub");var m=menu.querySelector(".dd-main");'
               . 'if(s)s.classList.remove("dd-open");if(m)m.classList.remove("dd-collapsed");});});';
            echo 'var csrf=' . json_encode($csrf) . ';';
            echo 'var endpoint=' . json_encode((string) $themeEndpoint) . ';';
            echo 'menu.querySelectorAll(".theme-btn").forEach(function(btn){';
            echo 'btn.addEventListener("click",function(e){e.stopPropagation();';
            echo 'var t=btn.dataset.theme;';
            echo 'if(t==="auto"){delete document.documentElement.dataset.theme;}';
            echo 'else{document.documentElement.dataset.theme=t;}';
            echo 'menu.querySelectorAll(".theme-btn").forEach(function(b){'
               . 'b.classList.toggle("active",b.dataset.theme===t);});';
            echo 'document.cookie="theme="+t+";path=/;max-age="+(365*86400)+";samesite=Lax";';
            echo 'var fd=new FormData();fd.append("action","change_theme");';
            echo 'fd.append("theme",t);fd.append("csrf_token",csrf);';
            echo 'fetch(endpoint,{method:"POST",body:fd}).catch(function(){});';
            echo '});});';
            echo '})();';
            echo '</script>';
        }
    }
}
