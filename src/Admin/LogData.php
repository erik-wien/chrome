<?php

declare(strict_types=1);

namespace Erikr\Chrome\Admin;

use mysqli;

/**
 * Data access for the admin Log tab.
 *
 * Reads auth_log (+ auth_accounts for username resolution).
 * Requires AUTH_DB_PREFIX.
 */
final class LogData
{
    /**
     * @param array{app?:string,context?:string,user?:string,from?:string,to?:string,q?:string,fail?:bool} $filters
     * @return array{rows: list<array<string,mixed>>, total: int, page: int, per_page: int}
     */
    public static function list(mysqli $con, int $page, int $perPage, array $filters): array
    {
        $prefix  = defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '';
        $lTable  = $prefix . 'auth_log';
        $aTable  = $prefix . 'auth_accounts';

        $where  = [];
        $types  = '';
        $params = [];

        if (!empty($filters['app'])) {
            $where[]  = 'l.origin = ?';
            $types   .= 's';
            $params[] = $filters['app'];
        }
        if (!empty($filters['context'])) {
            $where[]  = 'l.context = ?';
            $types   .= 's';
            $params[] = $filters['context'];
        }
        if (!empty($filters['user'])) {
            $where[]  = '(a.username LIKE ? OR ai.username LIKE ?)';
            $types   .= 'ss';
            $like     = '%' . self::escLike($filters['user']) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['from'])) {
            $where[]  = 'l.logTime >= ?';
            $types   .= 's';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[]  = 'l.logTime < (? + INTERVAL 1 DAY)';
            $types   .= 's';
            $params[] = $filters['to'] . ' 00:00:00';
        }
        if (!empty($filters['q'])) {
            $where[]  = 'l.activity LIKE ?';
            $types   .= 's';
            $params[] = '%' . self::escLike($filters['q']) . '%';
        }
        if (!empty($filters['fail'])) {
            $where[] = "l.context LIKE '%fail%'";
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset   = ($page - 1) * $perPage;

        $sql = "SELECT l.id, l.logTime, l.origin, l.context, l.activity,
                       INET_NTOA(l.ipAdress) AS ip,
                       CASE WHEN ai.username IS NOT NULL
                            THEN CONCAT(ai.username, ':', a.username)
                            ELSE a.username END AS username
                FROM {$lTable} l
                LEFT JOIN {$aTable} a  ON a.id  = l.idUser
                LEFT JOIN {$aTable} ai ON ai.id = l.impersonator_id
                {$whereSql}
                ORDER BY l.logTime DESC, l.id DESC
                LIMIT ? OFFSET ?";

        $stmt       = $con->prepare($sql);
        $typesPage  = $types . 'ii';
        $paramsPage = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($typesPage, ...$paramsPage);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'       => (int) $r['id'],
                'logTime'  => $r['logTime'],
                'origin'   => $r['origin'],
                'context'  => $r['context'],
                'activity' => $r['activity'],
                'ip'       => $r['ip'],
                'username' => $r['username'],
            ];
        }
        $stmt->close();

        $countSql = "SELECT COUNT(*) FROM {$lTable} l
                     LEFT JOIN {$aTable} a  ON a.id  = l.idUser
                     LEFT JOIN {$aTable} ai ON ai.id = l.impersonator_id
                     {$whereSql}";
        $cstmt = $con->prepare($countSql);
        if ($params) {
            $cstmt->bind_param($types, ...$params);
        }
        $cstmt->execute();
        $total = 0;
        $cstmt->bind_result($total);
        $cstmt->fetch();
        $cstmt->close();

        return ['rows' => $rows, 'total' => (int) $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** @return list<string> */
    public static function distinctApps(mysqli $con): array
    {
        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '') . 'auth_log';
        $out   = [];
        if ($res = $con->query("SELECT DISTINCT origin FROM {$table} WHERE origin <> '' ORDER BY origin")) {
            while ($row = $res->fetch_row()) {
                $out[] = $row[0];
            }
            $res->free();
        }
        return $out;
    }

    /** @return list<string> */
    public static function distinctContexts(mysqli $con): array
    {
        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : '') . 'auth_log';
        $out   = [];
        if ($res = $con->query("SELECT DISTINCT context FROM {$table} WHERE context <> '' ORDER BY context")) {
            while ($row = $res->fetch_row()) {
                $out[] = $row[0];
            }
            $res->free();
        }
        return $out;
    }

    private static function escLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
