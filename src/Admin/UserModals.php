<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

/**
 * Create + Edit modals for the admin users tab (canonical §15.2 pattern).
 *
 * Config:
 *   csrfToken   string   current CSRF token
 *   extraFields list<array{
 *     key: string,                           form field name + data-attr suffix
 *     label: string,                         label text
 *     type?: string,                         input type ('text'|'number'|'checkbox'|'select'; default 'text')
 *     options?: array<string,string>,        for type=select — value => label pairs
 *     default?: string|int,                  default value for create modal
 *     min?: int|string, max?: int|string,    for type=number
 *     help?: string                          optional helper text under the field
 *   }>                                       app-specific preferences surfaced in BOTH modals
 */
final class UserModals
{
    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg): void
    {
        $csrf        = (string) ($cfg['csrfToken'] ?? '');
        $extraFields = $cfg['extraFields'] ?? [];
        $h           = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
        <!-- Create Modal -->
        <div class="app-modal-backdrop" id="createModal" aria-hidden="true" hidden>
            <div class="app-modal-dialog app-modal-sm" role="dialog" aria-modal="true" aria-labelledby="createModalTitle" tabindex="-1">
                <div class="app-modal-header">
                    <div class="app-modal-header-row">
                        <h2 class="app-modal-title" id="createModalTitle">Benutzer anlegen</h2>
                        <button type="button" class="app-modal-close btn icon-btn" data-modal-close aria-label="Schließen"><span aria-hidden="true">&times;</span></button>
                    </div>
                </div>
                <form id="createForm">
                    <div class="app-modal-body">
                        <div id="createAlerts" class="app-modal-alerts"></div>
                        <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
                        <div class="form-group">
                            <label for="createUsername">Benutzername</label>
                            <input type="text" id="createUsername" name="username" class="form-control" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="createEmail">E-Mail</label>
                            <input type="email" id="createEmail" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="createRights">Rechte</label>
                            <select id="createRights" name="rights" class="form-control">
                                <option value="User">User</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <?php foreach ($extraFields as $f):
                            self::renderField($f, 'create', null, $h);
                        endforeach; ?>
                    </div>
                    <div class="app-modal-footer">
                        <button type="button" class="btn" data-modal-close>Abbrechen</button>
                        <button type="submit" class="btn btn-outline-danger">Anlegen &amp; einladen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="app-modal-backdrop" id="editModal" aria-hidden="true" hidden>
            <div class="app-modal-dialog app-modal-sm" role="dialog" aria-modal="true" aria-labelledby="editModalTitle" tabindex="-1">
                <div class="app-modal-header">
                    <div class="app-modal-header-row">
                        <h2 class="app-modal-title" id="editModalTitle">Benutzer bearbeiten: <span id="editUsername"></span></h2>
                        <button type="button" class="app-modal-close btn icon-btn" data-modal-close aria-label="Schließen"><span aria-hidden="true">&times;</span></button>
                    </div>
                </div>
                <form id="editForm">
                    <div class="app-modal-body">
                        <div id="editAlerts" class="app-modal-alerts"></div>
                        <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="id" id="editId">
                        <div class="form-group">
                            <label for="editEmail">E-Mail</label>
                            <input type="email" id="editEmail" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="editRights">Rechte</label>
                            <select id="editRights" name="rights" class="form-control">
                                <option value="User">User</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="editDisabled" name="disabled" value="1">
                            <label for="editDisabled">Deaktiviert</label>
                        </div>
                        <?php foreach ($extraFields as $f):
                            self::renderField($f, 'edit', null, $h);
                        endforeach; ?>
                    </div>
                    <div class="app-modal-footer">
                        <button type="button" class="btn" data-modal-close>Abbrechen</button>
                        <button type="submit" class="btn btn-outline-danger">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render the reset-password confirmation modal.
     * Must be called on every admin page that uses UsersTab (alongside render()).
     * The modal is populated and opened by wireResetPreview() in admin.js.
     */
    public static function renderResetPasswordModal(): void
    {
        ?>
        <div class="app-modal-backdrop" id="resetPasswordModal" aria-hidden="true" hidden>
            <div class="app-modal-dialog app-modal-sm" role="dialog" aria-modal="true"
                 aria-labelledby="resetPasswordModalTitle" tabindex="-1">
                <div class="app-modal-header">
                    <div class="app-modal-header-row">
                        <h2 class="app-modal-title" id="resetPasswordModalTitle">Passwort-Reset bestätigen</h2>
                        <button type="button" class="app-modal-close btn icon-btn" data-modal-close
                                aria-label="Schließen"><span aria-hidden="true">&times;</span></button>
                    </div>
                </div>
                <div class="app-modal-body">
                    <div id="resetPasswordAlerts" class="app-modal-alerts"></div>
                    <p>Benutzer: <strong id="resetPwUsername"></strong>
                       (<span id="resetPwEmail"></span>)</p>
                    <p>Hiermit wird:</p>
                    <ol>
                        <li>Eine neue Passwort-Reset-E-Mail versendet</li>
                        <li>Der Fehlversuche-Zähler auf 0 gesetzt</li>
                        <li>Folgende IPs aus der Blacklist entfernt:
                            <span id="resetPwIps" class="text-muted">—</span></li>
                    </ol>
                </div>
                <div class="app-modal-footer">
                    <input type="hidden" id="resetPwId" value="">
                    <button type="button" class="btn" data-modal-close>Abbrechen</button>
                    <button type="button" class="btn btn-outline-danger"
                            id="resetPwConfirm">Bestätigen</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $f
     */
    private static function renderField(array $f, string $prefix, mixed $value, callable $h): void
    {
        $key    = (string) $f['key'];
        $label  = (string) ($f['label'] ?? $key);
        $type   = (string) ($f['type'] ?? 'text');
        $id     = $prefix . ucfirst($key);
        $help   = (string) ($f['help'] ?? '');
        $dflt   = $f['default'] ?? '';

        if ($type === 'checkbox') {
            ?>
            <div class="form-check">
                <input type="checkbox" id="<?= $h($id) ?>" name="<?= $h($key) ?>" value="1">
                <label for="<?= $h($id) ?>"><?= $h($label) ?></label>
                <?php if ($help !== ''): ?><small class="form-text text-muted"><?= $h($help) ?></small><?php endif; ?>
            </div>
            <?php
            return;
        }

        if ($type === 'select') {
            $options = $f['options'] ?? [];
            ?>
            <div class="form-group">
                <label for="<?= $h($id) ?>"><?= $h($label) ?></label>
                <select id="<?= $h($id) ?>" name="<?= $h($key) ?>" class="form-control">
                    <?php foreach ($options as $val => $lab): ?>
                        <option value="<?= $h($val) ?>"<?= $prefix === 'create' && (string) $dflt === (string) $val ? ' selected' : '' ?>>
                            <?= $h($lab) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($help !== ''): ?><small class="form-text text-muted"><?= $h($help) ?></small><?php endif; ?>
            </div>
            <?php
            return;
        }

        $extraAttrs = '';
        if ($type === 'number') {
            if (isset($f['min'])) $extraAttrs .= ' min="' . $h((string) $f['min']) . '"';
            if (isset($f['max'])) $extraAttrs .= ' max="' . $h((string) $f['max']) . '"';
        }
        ?>
        <div class="form-group">
            <label for="<?= $h($id) ?>"><?= $h($label) ?></label>
            <input type="<?= $h($type) ?>" id="<?= $h($id) ?>" name="<?= $h($key) ?>"
                   class="form-control"
                   value="<?= $prefix === 'create' ? $h($dflt) : '' ?>"<?= $extraAttrs ?>>
            <?php if ($help !== ''): ?><small class="form-text text-muted"><?= $h($help) ?></small><?php endif; ?>
        </div>
        <?php
    }
}
