<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * base64 data-URI ছবি নিরাপদে সেভ করে।
 *  - শুধু হোয়াইটলিস্ট করা টাইপ
 *  - getimagesizefromstring() দিয়ে আসল ছবি যাচাই (nested-PHP আপলোড ঠেকায়)
 *  - Suppliers/Img/ এর ভিতরে সেভ (ওখানে PHP এক্সিকিউশন .htaccess দিয়ে বন্ধ)
 *  - path traversal থেকে সুরক্ষিত ডিলিট
 */
final class ImageUploader
{
    private const ALLOWED = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    /** ডিকোডের আগে base64 স্ট্রিং সাইজ ক্যাপ (~8MB বাইনারি ≈ 10.9MB base64) */
    private const MAX_BASE64_LEN = 11_500_000;

    /** ডিকোড করা আসল ছবির সর্বোচ্চ সাইজ (বাইট) — মেমরি/ডিস্ক DoS ঠেকায় */
    private const MAX_DECODED_BYTES = 8 * 1024 * 1024;

    /**
     * @return string SK_ROOT-relative path (DB-তে সেভ করার জন্য), ব্যর্থ হলে ''
     */
    public static function saveBase64(string $raw, string $subDir, string $prefix): string
    {
        if ($raw === '' || strlen($raw) > self::MAX_BASE64_LEN) {
            return '';
        }
        if (strpos($raw, ',') === false) {
            return '';
        }
        [$meta, $b64] = explode(',', $raw, 2);

        if (!preg_match('#^data:image/(\w+);base64#', $meta, $m)) {
            return '';
        }
        $type = strtolower($m[1]);
        if (!in_array($type, self::ALLOWED, true)) {
            return '';
        }

        $data = base64_decode(str_replace(' ', '+', $b64), true);
        if ($data === false || $data === '' || strlen($data) > self::MAX_DECODED_BYTES) {
            return '';
        }

        // আসল ছবি কিনা যাচাই — ভুয়া কন্টেন্ট ঠেকায়
        if (@getimagesizefromstring($data) === false) {
            return '';
        }

        $ext    = ($type === 'jpeg') ? 'jpg' : $type;
        $subDir = trim(preg_replace('#[^a-zA-Z0-9/_-]#', '', $subDir), '/');   // sanitize
        $dir    = SK_IMG . '/' . $subDir;

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            Logger::error('Image directory not writable: ' . $dir);
            return '';
        }

        $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $abs  = $dir . '/' . $name;

        if (@file_put_contents($abs, $data) === false) {
            return '';
        }
        @chmod($abs, 0644);

        return 'Suppliers/Img/' . $subDir . '/' . $name;
    }

    /** SK_ROOT-relative path ধরে নিরাপদে ফাইল মুছে (শুধু Img ফোল্ডারের ভিতরে) */
    public static function delete(string $relPath): void
    {
        if ($relPath === '') {
            return;
        }
        $full    = realpath(SK_ROOT . '/' . $relPath);
        $imgBase = realpath(SK_IMG);
        if ($full !== false && $imgBase !== false
            && str_starts_with($full, $imgBase . DIRECTORY_SEPARATOR)
            && is_file($full)) {
            @unlink($full);
        }
    }
}
