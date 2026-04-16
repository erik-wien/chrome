<?php
declare(strict_types=1);

namespace Erikr\Chrome;

use mysqli;

/**
 * Shared avatar endpoint.
 *
 * Serves the user's profile picture from `auth_accounts.img_blob`.
 * On miss (or when no id is supplied), returns a neutral "grey guy"
 * SVG silhouette so consumer pages never see a broken image.
 *
 * Each app's `web/avatar.php` becomes a 3-line stub:
 *
 *   require_once __DIR__ . '/../inc/initialize.php';
 *   \Erikr\Chrome\Avatar::serve($con);
 *
 * Public endpoint by design — caller can render any user's avatar without
 * an auth gate (matches admin user lists, presence panels, log Benutzer column).
 */
final class Avatar
{
    public static function serve(mysqli $con, ?int $uid = null): void
    {
        if ($uid === null) {
            $uid = (int) ($_GET['id'] ?? $_SESSION['id'] ?? 0);
        }

        if ($uid <= 0) {
            self::serveGreyGuy();
            return;
        }

        $table = (defined('AUTH_DB_PREFIX') ? AUTH_DB_PREFIX : 'jardyx_auth.') . 'auth_accounts';
        $sql   = 'SELECT img_blob FROM ' . $table
               . ' WHERE id = ? AND img_blob IS NOT NULL';
        $stmt  = $con->prepare($sql);
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['img_blob'])) {
            self::serveGreyGuy();
            return;
        }

        $blob = $row['img_blob'];
        $etag = '"' . md5($blob) . '"';
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            header('ETag: ' . $etag);
            http_response_code(304);
            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        header('ETag: ' . $etag);
        header('Content-Length: ' . strlen($blob));
        echo $blob;
    }

    private static function serveGreyGuy(): void
    {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" '
           . 'width="32" height="32" role="img" aria-label="">'
           . '<circle cx="16" cy="16" r="16" fill="#3a3a3a"/>'
           . '<circle cx="16" cy="13" r="5" fill="#9a9a9a"/>'
           . '<path d="M5 28 Q5 19 16 19 Q27 19 27 28 Z" fill="#9a9a9a"/>'
           . '</svg>';
    }
}
