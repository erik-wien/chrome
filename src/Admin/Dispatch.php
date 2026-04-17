<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

use mysqli;

/**
 * JSON API router for admin actions (canonical §15.1 interaction model).
 *
 * Usage in each app's api.php:
 *
 *     if (str_starts_with($action, 'admin_')) {
 *         \Erikr\Chrome\Admin\Dispatch::handle($con, $action, [
 *             'baseUrl' => $baseUrl,   // for invite/reset emails
 *             'selfId'  => (int) ($_SESSION['id'] ?? 0),
 *         ]);
 *         exit;
 *     }
 *
 * Every action is admin-guarded, POST, CSRF-verified, and returns
 * `application/json` with `{ok: bool, error?: string, ...}`.
 *
 * Assumes erikr/auth is loaded: auth_require(), admin_require(), csrf_verify(),
 * admin_create_user(), admin_edit_user(), admin_delete_user(),
 * admin_reset_password(), appendLog().
 */
final class Dispatch
{
    /** @param array{baseUrl?: string, selfId?: int, extraFields?: array<string,string>} $ctx */
    public static function handle(mysqli $con, string $action, array $ctx = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SESSION['rights'] ?? '') !== 'Admin') {
            self::out(['ok' => false, 'error' => 'forbidden'], 403);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::out(['ok' => false, 'error' => 'method_not_allowed'], 405);
            return;
        }
        if (!\csrf_verify()) {
            self::out(['ok' => false, 'error' => 'csrf'], 403);
            return;
        }

        try {
            match ($action) {
                'admin_user_list'                => self::userList($con),
                'admin_user_create'              => self::userCreate($con, $ctx),
                'admin_user_edit'                => self::userEdit($con, $ctx),
                'admin_user_delete'              => self::userDelete($con, $ctx),
                'admin_user_reset'               => self::userReset($con, $ctx),
                'admin_user_toggle_disabled'     => self::userToggleDisabled($con),
                'admin_user_revoke_totp'         => self::userRevokeTotp($con),
                'admin_user_reset_invalid'       => self::userResetInvalid($con),
                'admin_log_list'                 => self::logList($con),
                default                          => self::out(['ok' => false, 'error' => 'unknown_action'], 400),
            };
        } catch (\Throwable $e) {
            \appendLog($con, 'admin', 'Dispatch error: ' . $e->getMessage());
            self::out(['ok' => false, 'error' => 'server_error'], 500);
        }
    }

    private static function userList(mysqli $con): void
    {
        $page    = max(1, (int) ($_POST['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($_POST['per_page'] ?? 25)));
        $filter  = trim((string) ($_POST['filter'] ?? ''));
        $data    = Users::listExtended($con, $page, $perPage, $filter);
        self::out(['ok' => true] + $data);
    }

    /** @param array<string,mixed> $ctx */
    private static function userCreate(mysqli $con, array $ctx): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = trim((string) ($_POST['email']    ?? ''));
        $rights   = (string) ($_POST['rights'] ?? 'User');
        $baseUrl  = (string) ($ctx['baseUrl'] ?? '');
        if ($username === '' || $email === '') {
            self::out(['ok' => false, 'error' => 'missing_fields'], 400);
            return;
        }
        try {
            $id = \admin_create_user($con, $username, $email, $rights, $baseUrl);
        } catch (\mysqli_sql_exception $e) {
            self::out(['ok' => false, 'error' => 'duplicate_or_invalid'], 409);
            return;
        }
        self::out(['ok' => true, 'id' => $id]);
    }

    /** @param array<string,mixed> $ctx */
    private static function userEdit(mysqli $con, array $ctx): void
    {
        $id       = (int) ($_POST['id'] ?? 0);
        $email    = trim((string) ($_POST['email'] ?? ''));
        $rights   = (string) ($_POST['rights'] ?? 'User');
        $disabled = !empty($_POST['disabled']) ? 1 : 0;
        if ($id <= 0 || $email === '') {
            self::out(['ok' => false, 'error' => 'missing_fields'], 400);
            return;
        }
        $ok = \admin_edit_user($con, $id, $email, $rights, $disabled, 0, false);
        self::out(['ok' => (bool) $ok]);
    }

    /** @param array<string,mixed> $ctx */
    private static function userDelete(mysqli $con, array $ctx): void
    {
        $id     = (int) ($_POST['id'] ?? 0);
        $selfId = (int) ($ctx['selfId'] ?? ($_SESSION['id'] ?? 0));
        if ($id <= 0) {
            self::out(['ok' => false, 'error' => 'missing_id'], 400);
            return;
        }
        if ($id === $selfId) {
            self::out(['ok' => false, 'error' => 'cannot_delete_self'], 400);
            return;
        }
        $ok = \admin_delete_user($con, $id, $selfId);
        self::out(['ok' => (bool) $ok]);
    }

    /** @param array<string,mixed> $ctx */
    private static function userReset(mysqli $con, array $ctx): void
    {
        $id      = (int) ($_POST['id'] ?? 0);
        $baseUrl = (string) ($ctx['baseUrl'] ?? '');
        if ($id <= 0) {
            self::out(['ok' => false, 'error' => 'missing_id'], 400);
            return;
        }
        $ok = \admin_reset_password($con, $id, $baseUrl);
        self::out(['ok' => (bool) $ok]);
    }

    private static function userToggleDisabled(mysqli $con): void
    {
        $id       = (int) ($_POST['id'] ?? 0);
        $disabled = !empty($_POST['disabled']);
        if ($id <= 0) {
            self::out(['ok' => false, 'error' => 'missing_id'], 400);
            return;
        }
        $ok = Users::setDisabled($con, $id, $disabled);
        \appendLog($con, 'admin', 'User #' . $id . ($disabled ? ' disabled.' : ' enabled.'));
        self::out(['ok' => (bool) $ok, 'disabled' => $disabled ? 1 : 0]);
    }

    private static function userRevokeTotp(mysqli $con): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            self::out(['ok' => false, 'error' => 'missing_id'], 400);
            return;
        }
        $ok = Users::revokeTotp($con, $id);
        \appendLog($con, 'admin', 'User #' . $id . ' 2FA revoked.');
        self::out(['ok' => (bool) $ok]);
    }

    private static function userResetInvalid(mysqli $con): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            self::out(['ok' => false, 'error' => 'missing_id'], 400);
            return;
        }
        $ok = Users::resetInvalidLogins($con, $id);
        \appendLog($con, 'admin', 'User #' . $id . ' invalidLogins cleared.');
        self::out(['ok' => (bool) $ok]);
    }

    private static function logList(mysqli $con): void
    {
        $page    = max(1, (int) ($_POST['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($_POST['per_page'] ?? 50)));
        $filters = [
            'app'     => trim((string) ($_POST['app']     ?? '')),
            'context' => trim((string) ($_POST['context'] ?? '')),
            'user'    => trim((string) ($_POST['user']    ?? '')),
            'from'    => self::validDate((string) ($_POST['from'] ?? '')),
            'to'      => self::validDate((string) ($_POST['to']   ?? '')),
            'q'       => trim((string) ($_POST['q']       ?? '')),
            'fail'    => !empty($_POST['fail']),
        ];
        $data = LogData::list($con, $page, $perPage, $filters);
        $data['apps']     = LogData::distinctApps($con);
        $data['contexts'] = LogData::distinctContexts($con);
        self::out(['ok' => true] + $data);
    }

    private static function validDate(string $s): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
    }

    /** @param array<string,mixed> $payload */
    private static function out(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
