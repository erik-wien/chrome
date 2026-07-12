<?php

declare(strict_types=1);

namespace Erikr\Chrome;

/**
 * Erikr\Chrome\AvatarCropModal — canonical crop-modal markup for the avatar
 * uploader (css_library/js/avatar-cropper.js).
 *
 * avatar-cropper.js is a pure behaviour module: initAvatarCropper({...}) looks
 * up fixed element IDs and bails if any is missing, so each app had to supply
 * the modal HTML itself — three divergent inline copies existed (biblio clean,
 * Energie + wlmonitor with hardcoded inline styles; audit L4). This centralises
 * that markup in catalog classes (.app-modal-*).
 *
 * The element IDs are the fixed contract with avatar-cropper.js:
 *   avatarCropModal / avatarCropImage / avatarCropConfirm / avatarCropCancel
 * The app still emits the cropper <script> includes and its own
 * initAvatarCropper({...}) call (which carries the app-specific formAction +
 * csrfToken).
 */
final class AvatarCropModal
{
    /**
     * @param array{title?: string, cancelLabel?: string, confirmLabel?: string} $cfg
     */
    public static function render(array $cfg = []): void
    {
        $title   = (string) ($cfg['title']        ?? 'Profilbild zuschneiden');
        $cancel  = (string) ($cfg['cancelLabel']  ?? 'Abbrechen');
        $confirm = (string) ($cfg['confirmLabel'] ?? 'Speichern');

        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        echo '<div class="app-modal-backdrop" id="avatarCropModal" aria-hidden="true" role="dialog"'
           . ' aria-modal="true" aria-labelledby="avatarCropModal-titel" hidden>';
        echo   '<div class="app-modal-dialog">';
        echo     '<div class="app-modal-header"><div class="app-modal-header-row">';
        echo       '<h2 class="app-modal-title" id="avatarCropModal-titel">' . $h($title) . '</h2>';
        echo     '</div></div>';
        echo     '<div class="app-modal-body"><img id="avatarCropImage" alt=""></div>';
        echo     '<div class="app-modal-footer">';
        echo       '<button type="button" class="btn" id="avatarCropCancel">' . $h($cancel) . '</button>';
        echo       '<button type="button" class="btn btn-outline-danger" id="avatarCropConfirm">' . $h($confirm) . '</button>';
        echo     '</div>';
        echo   '</div>';
        echo '</div>';
    }
}
