<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

use mysqli;

/**
 * Data access for the admin users tab.
 *
 * Wraps auth_accounts + auth_invite_tokens + auth_log for the extended
 * columns the UI needs (activation state, last login, last IP, invalid
 * login count, 2FA enrolment, reset-pending).
 *
 * Requires AUTH_DB_PREFIX (from erikr/auth consumer setup).
 */
final class Users
{
    /**
     * @return array{users: list<array<string,mixed>>, total: int, page: int, per_page: int}
     */
    public static function listExtended(
        mysqli $con,
        int $page = 1,
        int $perPage = 25,
        string $filter = ''
    ): array {
        $prefix  = defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '';
        $aTable  = $prefix . 'auth_accounts';
        $tTable  = $prefix . 'auth_invite_tokens';
        $lTable  = $prefix . 'auth_log';
        $offset  = max(0, ($page - 1) * $perPage);

        $where   = '';
        $params  = [];
        $types   = '';
        if ($filter !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filter);
            $like    = '%' . $escaped . '%';
            $where   = 'WHERE (a.username LIKE ? OR a.email LIKE ?)';
            $params  = [$like, $like];
            $types   = 'ss';
        }

        $sql = "SELECT a.id, a.username, a.email, a.rights,
                       a.disabled, a.activation_code,
                       a.lastLogin, a.invalidLogins,
                       (a.totp_secret IS NOT NULL AND a.totp_secret <> '') AS has_totp,
                       (SELECT 1 FROM {$tTable} t
                          WHERE t.user_id = a.id AND t.expires_at > NOW()
                          LIMIT 1) AS has_pending_token,
                       (SELECT INET_NTOA(l.ipAdress) FROM {$lTable} l
                          WHERE l.idUser = a.id
                            AND l.context = 'login'
                            AND l.activity LIKE 'Login successful%'
                          ORDER BY l.logTime DESC LIMIT 1) AS lastIp
                FROM {$aTable} a
                {$where}
                ORDER BY a.username
                LIMIT ? OFFSET ?";

        $stmt     = $con->prepare($sql);
        $types   .= 'ii';
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $users = [];
        while ($row = $res->fetch_assoc()) {
            $status = 'activated';
            if ($row['activation_code'] !== 'activated') {
                $status = 'invite-pending';
            } elseif ((int) $row['has_pending_token'] === 1) {
                $status = 'reset-pending';
            }
            $users[] = [
                'id'             => (int) $row['id'],
                'username'       => (string) $row['username'],
                'email'          => (string) $row['email'],
                'rights'         => (string) $row['rights'],
                'disabled'       => (int) $row['disabled'],
                'activation'     => $status,
                'lastLogin'      => $row['lastLogin'] ?: null,
                'lastIp'         => $row['lastIp'] ?: null,
                'invalidLogins'  => (int) $row['invalidLogins'],
                'has_totp'       => (int) $row['has_totp'] === 1,
            ];
        }
        $stmt->close();

        if ($filter !== '') {
            $cstmt = $con->prepare(
                "SELECT COUNT(*) FROM {$aTable} a WHERE (a.username LIKE ? OR a.email LIKE ?)"
            );
            $cstmt->bind_param('ss', $like, $like);
        } else {
            $cstmt = $con->prepare("SELECT COUNT(*) FROM {$aTable}");
        }
        $cstmt->execute();
        $total = 0;
        $cstmt->bind_result($total);
        $cstmt->fetch();
        $cstmt->close();

        return ['users' => $users, 'total' => (int) $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Clear a user's invalidLogins counter (after admin confirmation).
     */
    public static function resetInvalidLogins(mysqli $con, int $userId): bool
    {
        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '') . 'auth_accounts';
        $stmt  = $con->prepare("UPDATE {$table} SET invalidLogins = 0 WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Toggle disabled flag. Returns new value (0|1) on success.
     */
    public static function setDisabled(mysqli $con, int $userId, bool $disabled): bool
    {
        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '') . 'auth_accounts';
        $d     = $disabled ? 1 : 0;
        $stmt  = $con->prepare("UPDATE {$table} SET disabled = ? WHERE id = ?");
        $stmt->bind_param('ii', $d, $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Clear TOTP secret so the user re-enrols on next login.
     */
    public static function revokeTotp(mysqli $con, int $userId): bool
    {
        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '') . 'auth_accounts';
        $stmt  = $con->prepare("UPDATE {$table} SET totp_secret = NULL WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
