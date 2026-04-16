<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

/**
 * Renders the canonical §15 Log tab (AJAX shell).
 *
 * Server-side only renders the filter form + empty table shell; rows, pagination,
 * and the App/Kontext dropdown options are populated by admin.js calling
 * api.php?action=admin_log_list.
 */
final class LogTab
{
    /** @param array<string,mixed> $cfg */
    public static function render(array $cfg = []): void
    {
        $h = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="card">
            <div class="card-header card-header-split">
                <span>Log (<span id="logTotal">…</span> Einträge)</span>
            </div>
            <div class="card-body">

                <form id="logFilterForm" class="log-filter-form"
                      style="display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem; align-items:end">
                    <div class="form-group">
                        <label for="log_app">App</label>
                        <select id="log_app" name="app" class="form-control">
                            <option value="">Alle</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="log_context">Kontext</label>
                        <select id="log_context" name="context" class="form-control">
                            <option value="">Alle</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="log_user">Benutzer</label>
                        <input type="text" id="log_user" name="user" class="form-control" placeholder="username">
                    </div>
                    <div class="form-group">
                        <label for="log_from">Von</label>
                        <input type="text" id="log_from" name="from" class="form-control" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="form-group">
                        <label for="log_to">Bis</label>
                        <input type="text" id="log_to" name="to" class="form-control" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="form-group" style="flex:1; min-width:14rem">
                        <label for="log_q">Suche in Aktivität</label>
                        <input type="text" id="log_q" name="q" class="form-control" placeholder="Text">
                    </div>
                    <div class="form-group" style="text-align:center">
                        <label for="log_fail">nur Fehler</label>
                        <input type="checkbox" id="log_fail" name="fail" value="1">
                    </div>
                    <div class="form-group" style="display:flex; gap:.5rem">
                        <button type="submit" class="btn">Filter</button>
                        <button type="reset" class="btn btn-outline-danger" id="logReset">Zurücksetzen</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-hover log-table">
                        <thead>
                            <tr>
                                <th>Zeit</th>
                                <th>App</th>
                                <th>Kontext</th>
                                <th>Benutzer</th>
                                <th>IP</th>
                                <th>Aktivität</th>
                            </tr>
                        </thead>
                        <tbody id="logTbody">
                            <tr><td colspan="6" class="text-muted">Lade…</td></tr>
                        </tbody>
                    </table>
                </div>

                <nav class="pagination" id="logPagination"></nav>

            </div>
        </div>
        <?php
    }
}
