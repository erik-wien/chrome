<?php
declare(strict_types=1);

namespace Erikr\Chrome;

use Erikr\Chrome\Admin\LogData;
use mysqli;

/**
 * "Log" — a user's own auth_log history (Header's activityHref target,
 * labeled "Log" in the dropdown since 2026-07-25; was "Meine Aktionen").
 * Reuses the optics of the admin Rule §15 Log tab (LogTab.php / admin.js)
 * but server-renders (no AJAX, no admin.js dependency) and, unlike the
 * admin tab, carries NO filter controls — the user only ever sees their
 * own rows.
 *
 * Security: the `userId` scope is FIXED and cannot be widened or replaced by
 * request input. Callers MUST pass the id from the session
 * (`$_SESSION['id']`), never from `$_GET`/`$_POST` — Activity::render()
 * itself never reads either superglobal for the user id, only (optionally)
 * `$_GET['page']` for pagination, which does not affect the row scope.
 *
 * Renders its own `<h1>` (default "Log", see `title` below) as the first
 * element it emits, before the log table — apps must NOT set a second
 * heading of their own above render().
 *
 * Card header vs. `<h1>`: when the `<h1>` is rendered (title !== null), the
 * card header below it carries ONLY the count ("1 Eintrag" / "N Einträge")
 * — it must NOT repeat the word "Log", or the page shows the same word
 * twice back to back (Fund 2026-07-25). When `title` is `null` (the app
 * supplies its own page heading and Activity renders no `<h1>` at all), the
 * word "Log" would otherwise vanish from the page entirely — so the card
 * header falls back to its original "Log (N Einträge)" form in that case,
 * keeping the label visible exactly once.
 *
 * Usage:
 *   \Erikr\Chrome\Activity::render([
 *       'con'    => $con,
 *       'userId' => (int) $_SESSION['id'],
 *   ]);
 *
 *   title    string|null  Optional. `<h1>` text, defaults to "Log". Pass
 *                          `null` to suppress the heading (only for apps
 *                          whose own page shell already renders one).
 */
final class Activity
{
    /** @param array{con: mysqli, userId: int, perPage?: int, page?: int, pageHref?: string, title?: string|null} $cfg */
    public static function render(array $cfg): void
    {
        $con      = $cfg['con'];
        $userId   = (int) ($cfg['userId'] ?? 0);
        $perPage  = max(1, (int) ($cfg['perPage'] ?? 20));
        $pageHrefTpl = (string) ($cfg['pageHref'] ?? '?page=%d');
        $title    = array_key_exists('title', $cfg) ? $cfg['title'] : 'Log';

        // page is display-only pagination input; never widen the userId scope
        // with it. Falls back to $_GET['page'] (server-rendered, no AJAX) when
        // the caller doesn't pass one explicitly.
        $page = array_key_exists('page', $cfg)
            ? (int) $cfg['page']
            : (int) ($_GET['page'] ?? 1);
        $page = max(1, $page);

        $data = LogData::list($con, $page, $perPage, ['userId' => $userId]);

        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        if ($title !== null) {
            echo '<h1>' . $e((string) $title) . '</h1>';
        }

        $total      = (int) $data['total'];
        $countLabel = $total === 1 ? '1 Eintrag' : $total . ' Einträge';

        echo '<div class="app-card">';
        echo '<div class="app-card-header app-card-header-split">';
        // See docblock above: with an <h1> already saying "Log", the card
        // header must not repeat the word — only when title === null (no
        // <h1> at all) does it keep the word so "Log" doesn't disappear.
        if ($title !== null) {
            echo '<span>' . $e($countLabel) . '</span>';
        } else {
            echo '<span>Log (' . $e($countLabel) . ')</span>';
        }
        echo '</div>';
        echo '<div class="app-card-body">';

        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-hover log-table">';
        echo '<thead><tr><th>Zeit</th><th>App</th><th>Kontext</th><th>Aktivität</th></tr></thead>';
        echo '<tbody>';
        if (empty($data['rows'])) {
            echo '<tr><td colspan="4" class="text-muted">Keine Einträge gefunden.</td></tr>';
        } else {
            foreach ($data['rows'] as $row) {
                echo '<tr>';
                echo '<td class="log-time">' . $e($row['logTime'] ?? '') . '</td>';
                echo '<td>' . $e($row['origin'] ?? '') . '</td>';
                echo '<td>' . $e($row['context'] ?? '') . '</td>';
                echo '<td class="log-activity">' . $e($row['activity'] ?? '') . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // .table-responsive

        // ── Pagination ────────────────────────────────────────────────────
        $lastPage = max(1, (int) ceil(((int) $data['total']) / $perPage));
        if ($lastPage > 1) {
            echo '<nav class="pagination">';
            for ($p = 1; $p <= $lastPage; $p++) {
                $href = str_replace('%d', (string) $p, $pageHrefTpl);
                $activeCls = $p === $page ? ' active' : '';
                echo '<a class="page-link' . $activeCls . '" href="' . $e($href) . '">' . $p . '</a>';
            }
            echo '</nav>';
        }

        echo '</div>'; // .app-card-body
        echo '</div>'; // .app-card
    }
}
