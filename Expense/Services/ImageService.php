<?php
declare(strict_types=1);

class ImageService {
    public function processAndSaveImage(?string $base64Data, string $expenseDate): ?string {
        if (empty($base64Data)) return null;

        $image_parts = explode(";base64,", $base64Data);
        if (count($image_parts) !== 2 || strlen($image_parts[1]) < 50) return null;

        $image_base64 = base64_decode($image_parts[1]);
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $mime_type    = $finfo->buffer($image_base64);

        if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'])) return null;

        $source_image = @imagecreatefromstring($image_base64);
        if ($source_image === false) return null;

        // __DIR__ = public_html/Services → uploads এক লেভেল উপরে
        $month_folder = date('Y-m', strtotime($expenseDate));
        $upload_dir   = __DIR__ . '/../../uploads/expense/' . $month_folder . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $width     = imagesx($source_image);
        $height    = imagesy($source_image);
        $new_width  = ($width > 800) ? 800 : $width;
        $new_height = ($width > 800) ? (int)floor($height * ($new_width / $width)) : $height;

        $virtual_image = imagecreatetruecolor($new_width, $new_height);
        $white_bg      = imagecolorallocate($virtual_image, 255, 255, 255);
        imagefill($virtual_image, 0, 0, $white_bg);
        imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $new_file_name = 'exp_' . time() . '_' . rand(1000, 9999) . '.jpg';
        $target_file   = $upload_dir . $new_file_name;
        imagejpeg($virtual_image, $target_file, 85);

        imagedestroy($source_image);
        if ($virtual_image !== $source_image) imagedestroy($virtual_image);

        // web path হিসেবে রিটার্ন (uploads/expense/YYYY-MM/filename.jpg)
        return 'uploads/expense/' . $month_folder . '/' . $new_file_name;
    }
}
