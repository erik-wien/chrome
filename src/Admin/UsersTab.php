<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

/**
 * Renders the canonical §15 Benutzerverwaltung tab.
 *
 * Call UsersTab::render([...]) from inside admin.php's #users tab panel.
 *
 * Config:
 *   users      list<array>  rows from Users::listExtended()['users']
 *   total      int          total row count (for pagination)
 *   page       int          current page (1-based)
 *   perPage    int          rows per page (default 25)
 *   filter     string       current filter string (username/email)
 *   selfId     int          current admin's user id (self-row actions hidden)
 *   pageUrl    callable(int $page, string $filter): string
 *                           builds paginator link URL (must preserve #users)
 *   extraColumns list<array{
 *     key: string,                          column key used in user rows
 *     label: string,                        th label
 *     render?: callable(array $user):string optional html renderer; htmlspecialchars the raw value when omitted
 *   }>                                      optional — app-specific extra columns (e.g. wlmonitor "Abfahrten")
 */
final class UsersTab
{
    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg): void
    {
        $users        = $cfg['users'] ?? [];
        $total        = (int) ($cfg['total'] ?? count($users));
        $page         = max(1, (int) ($cfg['page'] ?? 1));
        $perPage      = max(1, (int) ($cfg['perPage'] ?? 25));
        $filter       = (string) ($cfg['filter'] ?? '');
        $selfId       = (int) ($cfg['selfId'] ?? 0);
        $pageUrl      = $cfg['pageUrl'] ?? static fn(int $p, string $f): string =>
            'admin.php?page=' . $p . ($f !== '' ? '&filter=' . urlencode($f) : '') . '#users';
        $extraColumns = $cfg['extraColumns'] ?? [];
        $lastPage     = max(1, (int) ceil($total / $perPage));

        $h = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $statusBadge = static function (string $s) use ($h): string {
            return match ($s) {
                'activated'      => '<span class="badge badge-success">aktiv</span>',
                'invite-pending' => '<span class="badge badge-warning">Einladung offen</span>',
                'reset-pending'  => '<span class="badge badge-warning">Passwort-Reset offen</span>',
                default          => '<span class="badge">' . $h($s) . '</span>',
            };
        };

        ?>
        <div class="card">
            <div class="card-header card-header-split">
                <span>Benutzerverwaltung (<?= $h($total) ?>)</span>
                <button type="button" class="btn btn-outline-success btn-sm" data-modal-open="createModal">
                    + Benutzer anlegen
                </button>
            </div>
            <div class="card-body">

                <form method="get" action="admin.php" class="user-filter-form"
                      style="display:flex; gap:.5rem; margin-bottom:1rem">
                    <input type="text" name="filter" class="form-control"
                           placeholder="Benutzername oder E-Mail"
                           value="<?= $h($filter) ?>">
                    <button type="submit" class="btn">Suchen</button>
                    <?php if ($filter !== ''): ?>
                        <a href="admin.php#users" class="btn btn-outline-danger">Zurücksetzen</a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>E-Mail</th>
                            <th>Rechte</th>
                            <th>Status</th>
                            <th>Deaktiviert</th>
                            <th>Letzter Login</th>
                            <th>IP</th>
                            <th title="Fehlgeschlagene Login-Versuche">Fehlversuche</th>
                            <?php foreach ($extraColumns as $c): ?>
                                <th><?= $h($c['label']) ?></th>
                            <?php endforeach; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $uid       = (int) $u['id'];
                        $isSelf    = $uid === $selfId;
                        $disabled  = (int) ($u['disabled'] ?? 0);
                        $invalid   = (int) ($u['invalidLogins'] ?? 0);
                        $hasTotp   = !empty($u['has_totp']);
                        $lastLogin = $u['lastLogin'] ?? null;
                        $lastIp    = $u['lastIp'] ?? null;
                    ?>
                        <tr data-user-row="<?= $uid ?>">
                            <td><?= $h($u['username']) ?></td>
                            <td><?= $h($u['email']) ?></td>
                            <td><?= $h($u['rights']) ?></td>
                            <td><?= $statusBadge((string) ($u['activation'] ?? 'activated')) ?></td>
                            <td>
                                <?= $disabled
                                    ? '<span class="badge badge-danger">ja</span>'
                                    : '<span class="badge badge-success">nein</span>' ?>
                            </td>
                            <td><?= $lastLogin ? $h($lastLogin) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $lastIp ? '<code>' . $h($lastIp) . '</code>' : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if ($invalid > 0): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-invalid-reset"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>"
                                            data-count="<?= $invalid ?>"
                                            title="Klicken zum Zurücksetzen">
                                        <?= $invalid ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($extraColumns as $c):
                                $key = $c['key'];
                                $val = $u[$key] ?? '';
                                $html = isset($c['render']) && is_callable($c['render'])
                                    ? (string) $c['render']($u)
                                    : $h($val);
                            ?>
                                <td><?= $html ?></td>
                            <?php endforeach; ?>
                            <td style="white-space:nowrap">
                                <button type="button" class="btn btn-sm btn-edit"
                                        data-modal-open="editModal"
                                        data-id="<?= $uid ?>"
                                        data-username="<?= $h($u['username']) ?>"
                                        data-email="<?= $h($u['email']) ?>"
                                        data-rights="<?= $h($u['rights']) ?>"
                                        data-disabled="<?= $disabled ?>"
                                        <?php foreach ($extraColumns as $c):
                                            $k = $c['key']; ?>
                                            data-<?= $h($k) ?>="<?= $h($u[$k] ?? '') ?>"
                                        <?php endforeach; ?>>
                                    Bearbeiten
                                </button>
                                <?php if (!$isSelf): ?>
                                    <button type="button" class="btn btn-sm btn-toggle-disabled"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>"
                                            data-disabled="<?= $disabled ?>">
                                        <?= $disabled ? 'Aktivieren' : 'Deaktivieren' ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-reset"
                                        data-id="<?= $uid ?>"
                                        data-username="<?= $h($u['username']) ?>">
                                    Passwort-Reset
                                </button>
                                <?php if ($hasTotp): ?>
                                    <button type="button" class="btn btn-sm btn-revoke-totp"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>">
                                        2FA widerrufen
                                    </button>
                                <?php endif; ?>
                                <?php if (!$isSelf): ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>">
                                        Löschen
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="<?= 9 + count($extraColumns) ?>" class="text-muted">
                                Keine Benutzer gefunden.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($lastPage > 1): ?>
                    <nav class="pagination">
                        <?php for ($p = 1; $p <= $lastPage; $p++): ?>
                            <a class="page-link<?= $p === $page ? ' active' : '' ?>"
                               href="<?= $h($pageUrl($p, $filter)) ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
