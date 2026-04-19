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

        $statusIcon = static function (string $s) use ($h): string {
            return match ($s) {
                'activated'      => '<span class="ui-icon ui-icon-check is-success" aria-label="aktiv" title="aktiv"></span>',
                'invite-pending' => '<span class="ui-icon ui-icon-hourglass is-warning" aria-label="Einladung offen" title="Einladung offen"></span>',
                'reset-pending'  => '<span class="ui-icon ui-icon-hourglass is-warning" aria-label="Passwort-Reset offen" title="Passwort-Reset offen"></span>',
                default          => '<span class="ui-icon" aria-label="' . $h($s) . '" title="' . $h($s) . '"></span>',
            };
        };
        $disabledIcon = static fn(int $d): string => $d
            ? '<span class="ui-icon ui-icon-close is-danger" aria-label="deaktiviert" title="deaktiviert"></span>'
            : '<span class="ui-icon ui-icon-check is-success" aria-label="aktiv" title="aktiv"></span>';

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
                            <th style="text-align:center">Status</th>
                            <th style="text-align:center">Deaktiviert</th>
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
                            <td style="text-align:center"><?= $statusIcon((string) ($u['activation'] ?? 'activated')) ?></td>
                            <td style="text-align:center"><?= $disabledIcon($disabled) ?></td>
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
                                <button type="button" class="btn btn-sm btn-icon btn-edit"
                                        title="Bearbeiten" aria-label="Bearbeiten"
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
                                    <span class="ui-icon ui-icon-edit" aria-hidden="true"></span>
                                </button>
                                <?php if (!$isSelf): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-toggle-disabled"
                                            title="<?= $disabled ? 'Aktivieren' : 'Deaktivieren' ?>"
                                            aria-label="<?= $disabled ? 'Aktivieren' : 'Deaktivieren' ?>"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>"
                                            data-disabled="<?= $disabled ?>">
                                        <span class="ui-icon ui-icon-<?= $disabled ? 'check' : 'ban' ?>" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-icon btn-reset"
                                        title="Passwort-Reset" aria-label="Passwort-Reset"
                                        data-id="<?= $uid ?>"
                                        data-username="<?= $h($u['username']) ?>">
                                    <span class="ui-icon ui-icon-key" aria-hidden="true"></span>
                                </button>
                                <?php if ($hasTotp): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-revoke-totp"
                                            title="2FA widerrufen" aria-label="2FA widerrufen"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>">
                                        <span class="ui-icon ui-icon-shield-off" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>
                                <?php if (!$isSelf): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger btn-delete"
                                            title="Löschen" aria-label="Löschen"
                                            data-id="<?= $uid ?>"
                                            data-username="<?= $h($u['username']) ?>">
                                        <span class="ui-icon ui-icon-delete" aria-hidden="true"></span>
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
