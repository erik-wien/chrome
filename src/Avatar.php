<?php
declare(strict_types=1);

namespace Erikr\Chrome;

use mysqli;

/**
 * Shared avatar endpoint.
 *
 * Serves the user's profile picture from `auth_accounts.img_blob`. On miss
 * (or when no id is supplied), returns a neutral "grey guy" SVG so consumer
 * pages never see a broken image.
 *
 * Each app's `web/avatar.php` becomes a 3-line stub:
 *
 *   require_once __DIR__ . '/../inc/initialize.php';
 *   \Erikr\Chrome\Avatar::serve($con);
 *
 * Public endpoint by design — any user's avatar renders without an auth gate
 * (matches admin user lists, presence panels, log Benutzer column).
 */
final class Avatar
{
    /** JPEG encoded by chrome's AvatarUpload pipeline (205x205, q85). */
    private const CANONICAL_MIME = 'image/jpeg';

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
        try {
            $stmt = $con->prepare('SELECT img_blob FROM ' . $table . ' WHERE id = ? AND img_blob IS NOT NULL');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log('Erikr\Chrome\Avatar: ' . $e->getMessage());
            self::serveGreyGuy();
            return;
        }

        if (!$row || empty($row['img_blob'])) {
            self::serveGreyGuy();
            return;
        }

        self::sendBinary($row['img_blob'], self::detectMime($row['img_blob']));
    }

    /**
     * Detect image MIME by leading magic bytes. Legacy blobs on shared hosts
     * pre-date migration 09 (avatar_simplify) and may still be PNG/GIF/WEBP;
     * new uploads are always JPEG. Unknown → fall back to canonical JPEG.
     */
    private static function detectMime(string $blob): string
    {
        $head = substr($blob, 0, 12);
        if (strncmp($head, "\xFF\xD8\xFF", 3) === 0)                       return 'image/jpeg';
        if (strncmp($head, "\x89PNG\r\n\x1A\n", 8) === 0)                  return 'image/png';
        if (strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0) return 'image/gif';
        if (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') return 'image/webp';
        return self::CANONICAL_MIME;
    }

    private static function sendBinary(string $blob, string $mime): void
    {
        $etag = '"' . md5($blob) . '"';
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            header('ETag: ' . $etag);
            http_response_code(304);
            return;
        }

        // Shared hosts (e.g. world4you) run zlib.output_compression=On by
        // default, which re-wraps the response and invalidates a manually
        // set Content-Length — a previous version of this endpoint emitted
        // the uncompressed length and the client received a truncated body.
        // Disable compression + drain all buffers before writing binary.
        self::disableTransforms();

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=86400');
        header('ETag: ' . $etag);
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

    private static function disableTransforms(): void
    {
        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 'Off');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}
