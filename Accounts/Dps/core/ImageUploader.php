<?php
/**
 * core/ImageUploader.php
 * ─────────────────────────────────────────
 * একাউন্ট ছবি আপলোড হ্যান্ডলার।
 * শুধুমাত্র PHP-এর built-in GD এক্সটেনশন ব্যবহার করা হয়েছে —
 * কোনো থার্ড-পার্টি লাইব্রেরি/প্যাকেজ নেই (requirement অনুযায়ী)।
 *
 * নিরাপত্তা:
 *  - $_FILES['error'] চেক
 *  - সাইজ হার্ড লিমিট (১০ এমবি) — আপলোডের আগেই reject
 *  - getimagesize() দিয়ে প্রকৃত ইমেজ কিনা যাচাই (শুধু extension/MIME হেডার বিশ্বাস করা হয় না)
 *  - Whitelisted MIME টাইপ (jpeg/png/webp)
 *  - GD দিয়ে re-encode করার ফলে embedded script/EXIF/malicious payload ঝরে যায়
 *  - র‍্যান্ডম ফাইলনেম (path traversal / overwrite প্রতিরোধ)
 */
declare(strict_types=1);

final class ImageUploader
{
    /**
     * @return string সেভ হওয়া ফাইলের নাম (শুধু filename, path নয়) — DB-তে এটাই রাখুন
     * @throws DpsUserException ইউজার-ফেসিং এরর মেসেজের জন্য
     */
    public static function handle(array $file): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new DpsUserException('আপলোড রিকোয়েস্ট সঠিক নয়।');
        }

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new DpsUserException('কোনো ছবি সিলেক্ট করা হয়নি।');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new DpsUserException('ছবি আপলোডে সমস্যা হয়েছে (কোড: ' . $file['error'] . ')।');
        }

        if ($file['size'] <= 0 || $file['size'] > DPS_MAX_UPLOAD_BYTES) {
            throw new DpsUserException('ছবির সাইজ ১০ এমবি-এর বেশি হতে পারবে না।');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new DpsUserException('অবৈধ আপলোড।');
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            throw new DpsUserException('ফাইলটি বৈধ ছবি নয়।');
        }
        [$width, $height, $type] = $imgInfo;
        $mime = $imgInfo['mime'] ?? '';

        if (!in_array($mime, DPS_ALLOWED_MIME, true)) {
            throw new DpsUserException('শুধুমাত্র JPG, PNG বা WEBP ছবি আপলোড করা যাবে।');
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : false,
            default        => false,
        };
        if ($source === false) {
            throw new DpsUserException('ছবি প্রসেস করা যায়নি।');
        }

        // ── Resize (aspect ratio বজায় রেখে) ──
        $maxDim = DPS_MAX_IMAGE_DIMENSION;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio    = min($maxDim / $width, $maxDim / $height);
            $newW     = (int)round($width * $ratio);
            $newH     = (int)round($height * $ratio);
            $resized  = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        if (!is_dir(DPS_UPLOAD_DIR)) {
            @mkdir(DPS_UPLOAD_DIR, 0755, true);
        }

        // র‍্যান্ডম, অনুমানযোগ্য-নয় ফাইলনেম — সবসময় .jpg-তে re-encode
        $filename = 'acc_' . bin2hex(random_bytes(12)) . '.jpg';
        $fullPath = DPS_UPLOAD_DIR . '/' . $filename;

        $saved = imagejpeg($source, $fullPath, 85);
        imagedestroy($source);

        if (!$saved) {
            throw new DpsUserException('ছবি সেভ করা যায়নি — সার্ভার পারমিশন চেক করুন।');
        }

        return $filename;
    }

    public static function urlFor(?string $filename): ?string
    {
        if (empty($filename)) {
            return null;
        }
        return rtrim(DPS_UPLOAD_URL, '/') . '/' . rawurlencode($filename);
    }

    public static function delete(?string $filename): void
    {
        if (empty($filename)) {
            return;
        }
        $path = DPS_UPLOAD_DIR . '/' . basename($filename); // basename() → path traversal প্রতিরোধ
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
