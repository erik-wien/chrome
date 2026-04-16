<?php
declare(strict_types=1);

namespace Erikr\Chrome;

use mysqli;

/**
 * Avatar upload processing.
 *
 * Decodes an uploaded image via GD, center-crops to a square, resizes to
 * 205x205, re-encodes as JPEG quality 85, and persists via the auth
 * library's avatar_store function. The DB sees only the final JPEG —
 * MIME is canonical, filename is discarded.
 *
 * Consumers:
 *
 *   $res = \Erikr\Chrome\AvatarUpload::handle($con, (int) $_SESSION['id'], $_FILES['avatar']);
 *   if (!$res['ok']) { addAlert('danger', $messages[$res['error']] ?? 'Fehler'); }
 *
 * Error tokens (untranslated — consumer maps to user-facing strings):
 *   upload_failed, too_large, not_image, decode_failed, too_small, encode_failed
 */
final class AvatarUpload
{
    public const MAX_BYTES  = 5 * 1024 * 1024;
    public const MIN_SIDE   = 64;
    public const OUT_SIDE   = 205;
    public const JPEG_Q     = 85;

    /** @param array{tmp_name?: string, size?: int, error?: int}|null $file */
    public static function handle(mysqli $con, int $userId, ?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'upload_failed'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'not_image'];
        }

        [$w, $h] = [$info[0], $info[1]];
        if ($w < self::MIN_SIDE || $h < self::MIN_SIDE) {
            return ['ok' => false, 'error' => 'too_small'];
        }

        $src = self::decode($file['tmp_name'], $info['mime']);
        if (!$src) {
            return ['ok' => false, 'error' => 'decode_failed'];
        }

        $side   = min($w, $h);
        $srcX   = (int) (($w - $side) / 2);
        $srcY   = (int) (($h - $side) / 2);
        $dst    = imagecreatetruecolor(self::OUT_SIDE, self::OUT_SIDE);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::OUT_SIDE, self::OUT_SIDE, $side, $side);

        ob_start();
        $ok = imagejpeg($dst, null, self::JPEG_Q);
        $jpeg = ob_get_clean();

        if (!$ok || !is_string($jpeg) || $jpeg === '') {
            return ['ok' => false, 'error' => 'encode_failed'];
        }

        \auth_avatar_store($con, $userId, $jpeg);
        return ['ok' => true, 'size' => strlen($jpeg)];
    }

    public static function clear(mysqli $con, int $userId): void
    {
        \auth_avatar_clear($con, $userId);
    }

    /** @return \GdImage|false */
    private static function decode(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };
    }
}
