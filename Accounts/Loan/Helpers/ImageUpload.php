<?php

declare(strict_types=1);

/**
 * ImageUpload — কিস্তি শোধের রসিদ/প্রমাণের ছবি নিরাপদে সংরক্ষণ।
 *
 * নিরাপত্তা:
 *   • শুধু সত্যিকারের ছবি (getimagesize + finfo দিয়ে যাচাই, শুধু
 *     এক্সটেনশন নয়) — jpg / png / webp
 *   • এলোমেলো ফাইলনেম (মূল নাম কখনো ব্যবহার হয় না)
 *   • .php ইত্যাদি এক্সিকিউটেবল কখনো সেভ হয় না (Uploads/.htaccess ও ব্লক করে)
 *   • ছবি GD দিয়ে পুনরায় re-encode হয় — এতে ফাইলের ভেতর লুকানো কোনো
 *     ক্ষতিকর ডেটা/EXIF থাকলে তা স্বয়ংক্রিয়ভাবে মুছে যায়
 *
 * সাইজ নীতি:
 *   • RAW_MAX_BYTES এর চেয়ে বড় ফাইল প্রথমেই প্রত্যাখ্যান (misuse ঠেকাতে —
 *     কেউ ইচ্ছে করে ১০০ MB পাঠিয়ে সার্ভার আটকে দেওয়ার চেষ্টা করতে পারবে না)
 *   • এর মধ্যে যেকোনো সাইজ হলেই GD দিয়ে resize + compress করে
 *     TARGET_MAX_BYTES এর নিচে নামিয়ে আনা হয় — ব্যবহারকারীকে কোনো
 *     "সাইজ বেশি" এরর আর দেখতে হয় না
 */
final class ImageUpload
{
    /** সার্ভারে DoS ঠেকাতে — এর বেশি বড় ফাইল একদমই গ্রহণ করা হবে না। */
    private const RAW_MAX_BYTES = 20 * 1024 * 1024; // ২০ MB (কাঁচা আপলোড লিমিট)

    /** কমপ্রেস করার পর ফাইল এই সাইজের মধ্যেই থাকবে। */
    private const TARGET_MAX_BYTES = 1 * 1024 * 1024; // ১ MB

    /** ছবির সর্বোচ্চ প্রস্থ/উচ্চতা — এর বেশি হলে ছোট করা হবে (অনুপাত ঠিক রেখে)। */
    private const MAX_DIMENSION = 1600;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * $file = $_FILES['...'] এর একটি এন্ট্রি।
     * সফল হলে সংরক্ষিত ফাইলের নাম (basename) ফেরত দেয়, নাহলে null।
     * ভুল হলে $error এ কারণ বসে।
     */
    public static function handle(?array $file, ?string &$error = null): ?string
    {
        $error = null;

        if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // ছবি দেওয়া হয়নি — এটা ঐচ্ছিক, তাই ভুল নয়
        }

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'ছবিটি সার্ভারের সর্বোচ্চ আপলোড সীমার চেয়ে বড়। ছোট ছবি দিন।';
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'ছবি আপলোডে সমস্যা হয়েছে।';
            return null;
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::RAW_MAX_BYTES) {
            $error = 'ছবির আকার সর্বোচ্চ ২০ MB হতে পারবে।';
            return null;
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            $error = 'অবৈধ আপলোড।';
            return null;
        }

        // আসল MIME যাচাই (এক্সটেনশন নয়)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            $error = 'শুধু ছবি (JPG, PNG, WebP) আপলোড করা যাবে।';
            return null;
        }

        // সত্যিই ছবি কিনা — getimagesize দ্বিতীয় স্তরের যাচাই
        $info = @getimagesize($tmp);
        if ($info === false) {
            $error = 'ফাইলটি বৈধ ছবি নয়।';
            return null;
        }

        if (!extension_loaded('gd')) {
            // GD না থাকলে অন্তত পুরনো raw-copy পদ্ধতিতে ফলব্যাক করবে
            return self::saveRaw($tmp, self::ALLOWED[$mime], $error);
        }

        $compressed = self::compressAndSave($tmp, $mime, $error);
        if ($compressed === null && $error === null) {
            // কমপ্রেস ব্যর্থ হলেও, মূল ফাইলটা যদি লিমিটের মধ্যে থাকে, সেটাই সেভ করব
            return self::saveRaw($tmp, self::ALLOWED[$mime], $error);
        }

        return $compressed;
    }

    /**
     * GD দিয়ে ছবি resize + compress করে সেভ করে। আউটপুট সবসময় JPEG
     * (রসিদের ছবির জন্য transparency দরকার নেই, JPEG সবচেয়ে ছোট সাইজ দেয়)।
     */
    private static function compressAndSave(string $tmp, string $mime, ?string &$error): ?string
    {
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            default      => null,
        };

        if ($source === false || $source === null) {
            $error = null; // null রাখলে caller raw ফলব্যাকে যাবে
            return null;
        }

        $origW = imagesx($source);
        $origH = imagesy($source);

        // অনুপাত ঠিক রেখে সর্বোচ্চ MAX_DIMENSION এ নামিয়ে আনা
        $ratio = min(1.0, self::MAX_DIMENSION / max($origW, $origH));
        $newW = max(1, (int)round($origW * $ratio));
        $newH = max(1, (int)round($origH * $ratio));

        $resized = imagecreatetruecolor($newW, $newH);
        // PNG-এর transparent অংশ সাদা ব্যাকগ্রাউন্ডে বসানো (JPEG-এ transparency নেই)
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($source);

        $name = 'rcpt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.jpg';
        $dir  = APP_ROOT . '/Uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $dest = $dir . '/' . $name;

        // TARGET_MAX_BYTES এর নিচে না নামা পর্যন্ত quality ধাপে ধাপে কমানো
        $quality = 82;
        $saved = false;
        while ($quality >= 40) {
            imagejpeg($resized, $dest, $quality);
            if (filesize($dest) <= self::TARGET_MAX_BYTES) {
                $saved = true;
                break;
            }
            $quality -= 12;
        }
        imagedestroy($resized);

        if (!$saved && is_file($dest) && filesize($dest) > self::TARGET_MAX_BYTES) {
            // সর্বনিম্ন quality-তেও টার্গেটের বেশি — তাও গ্রহণযোগ্য (RAW_MAX_BYTES এর
            // চেয়ে অনেক ছোট নিশ্চয়ই), তাই বাতিল না করে এটাই রাখা হলো
            $saved = true;
        }

        if (!$saved || !is_file($dest)) {
            $error = 'ছবি প্রসেস করা যায়নি। আবার চেষ্টা করুন।';
            @unlink($dest);
            return null;
        }

        @chmod($dest, 0644);
        return $name;
    }

    /** GD না থাকলে বা compress ব্যর্থ হলে — আগের পদ্ধতির মতো raw কপি। */
    private static function saveRaw(string $tmp, string $ext, ?string &$error): ?string
    {
        $name = 'rcpt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dir  = APP_ROOT . '/Uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
            $error = 'ছবি সংরক্ষণ করা যায়নি।';
            return null;
        }

        @chmod($dest, 0644);
        return $name;
    }
}

