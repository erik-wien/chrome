<?php
declare(strict_types=1);

namespace Erikr\Chrome;

use Erikr\Chrome\Admin\LogData;
use mysqli;

/**
 * "Meine Aktionen" — a user's own auth_log history (Header's activityHref
 * target). Reuses the optics of the admin Rule §15 Log tab (LogTab.php /
 * admin.js) but server-renders (no AJAX, no admin.js dependency) and, unlike
 * the admin tab, carries NO filter controls — the user only ever sees their
 * own rows.
 *
 * Security: the `userId` scope is FIXED and cannot be widened or replaced by
 * request input. Callers MUST pass the id from the session
 * (`$_SESSION['id']`), never from `$_GET`/`$_POST` — Activity::render()
 * itself never reads either superglobal for the user id, only (optionally)
 * `$_GET['page']` for pagination, which does not affect the row scope.
 *
 * Usage:
 *   \Erikr\Chrome\Activity::render([
 *       'con'    => $con,
 *       'userId' => (int) $_SESSION['id'],
 *   ]);
 */
final class Activity
{
    /** @param array{con: mysqli, userId: int, perPage?: int, page?: int, pageHref?: string} $cfg */
    public static function render(array $cfg): void
    {
        $con      = $cfg['con'];
        $userId   = (int) ($cfg['userId'] ?? 0);
        $perPage  = max(1, (int) ($cfg['perPage'] ?? 20));
        $pageHrefTpl = (string) ($cfg['pageHref'] ?? '?page=%d');

        // page is display-only pagination input; never widen the userId scope
        // with it. Falls back to $_GET['page'] (server-rendered, no AJAX) when
        // the caller doesn't pass one explicitly.
        $page = array_key_exists('page', $cfg)
            ? (int) $cfg['page']
            : (int) ($_GET['page'] ?? 1);
        $page = max(1, $page);

        $data = LogData::list($con, $page, $perPage, ['userId' => $userId]);

        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        echo '<div class="app-card">';
        echo '<div class="app-card-header app-card-header-split">';
        echo '<span>Meine Aktionen (' . (int) $data['total'] . ' Einträge)</span>';
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
