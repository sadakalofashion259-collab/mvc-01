<?php
declare(strict_types=1);

/**
 * ImageUploader — Safely persists base64 (data-URI) images.
 *
 * Hardening applied:
 *   - 5 MB decoded-size ceiling (rejects oversized payloads)
 *   - Content is verified with getimagesizefromstring(); anything that is
 *     not a real JPEG/PNG/WebP is rejected (extension alone is never trusted)
 *   - File names are generated from random_bytes (no user influence)
 *   - Directories are created 0755 (never world-writable)
 *   - All writes are confined beneath the module root (path-traversal safe)
 */
final class ImageUploader
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /** Allowed real MIME types mapped to stored extensions. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Absolute module root, e.g. /home/sadakalo/public_html/Customer */
    private string $moduleRoot;

    public function __construct(?string $moduleRoot = null)
    {
        $this->moduleRoot = rtrim($moduleRoot ?? dirname(__DIR__), '/');
    }

    /**
     * Save a base64 data-URI image.
     *
     * @param string $rawBase64  e.g. "data:image/jpeg;base64,...."
     * @param string $folder     Relative folder such as "uploads/profile/2026-07/"
     * @param string $prefix     File-name prefix such as "cust"
     * @return string            Stored relative path, or '' on failure.
     */
    public function saveBase64Image(string $rawBase64, string $folder, string $prefix): string
    {
        if (!str_contains($rawBase64, ',')) {
            return '';
        }

        $encoded = explode(',', $rawBase64, 2)[1];
        $encoded = str_replace(' ', '+', $encoded);
        $binary  = base64_decode($encoded, true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            return '';
        }

        // Verify the bytes really are an image of an allowed type.
        $info = @getimagesizefromstring($binary);
        if ($info === false || !isset(self::ALLOWED_TYPES[$info['mime'] ?? ''])) {
            return '';
        }
        $extension = self::ALLOWED_TYPES[$info['mime']];

        // Confine the target folder inside uploads/ under the module root.
        $folder = trim($folder, '/');
        if ($folder === '' || !str_starts_with($folder, 'uploads')
            || str_contains($folder, '..')) {
            return '';
        }

        $targetDir = $this->moduleRoot . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return '';
        }

        $prefix       = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix) ?: 'img';
        $fileName     = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        $relativePath = $folder . '/' . $fileName;

        if (file_put_contents($this->moduleRoot . '/' . $relativePath, $binary) === false) {
            return '';
        }

        return $relativePath;
    }

    /**
     * Delete a previously stored image — only if it truly resides inside
     * the module's uploads/ directory (defends against poisoned DB paths).
     */
    public function deleteStoredImage(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $uploadsRoot = realpath($this->moduleRoot . '/uploads');
        $target      = realpath($this->moduleRoot . '/' . ltrim($relativePath, '/'));

        if ($uploadsRoot !== false
            && $target !== false
            && str_starts_with($target, $uploadsRoot . '/')
            && is_file($target)) {
            @unlink($target);
        }
    }
}
