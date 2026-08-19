<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════
// IMAGE RESIZER — GD Library দিয়ে backend resize (max 800×800px)
// Profile/Services/ImageResizer.php
// ═══════════════════════════════════════════════════════════════
final class ImageResizer {

    private const MAX_DIMENSION   = 800;
    private const JPEG_QUALITY    = 85;
    private const PNG_COMPRESSION = 6;

    public function resizeAndSave(
        string $sourceTmpPath,
        string $mimeType,
        string $destinationPath
    ): bool {
        if (!extension_loaded('gd')) return false;

        $imageInfo = @getimagesize($sourceTmpPath);
        if ($imageInfo === false) return false;

        $sourceImage = match($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourceTmpPath),
            'image/png'               => @imagecreatefrompng($sourceTmpPath),
            'image/gif'               => @imagecreatefromgif($sourceTmpPath),
            'image/webp'              => @imagecreatefromwebp($sourceTmpPath),
            default                   => false,
        };
        if ($sourceImage === false) return false;

        $originalWidth  = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        [$newWidth, $newHeight] = $this->calculateNewDimensions($originalWidth, $originalHeight);

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($resizedImage === false) { imagedestroy($sourceImage); return false; }

        $needsTransparency = in_array($mimeType, ['image/png', 'image/gif'], strict: true);
        if ($needsTransparency) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        } else {
            $white = imagecolorallocate($resizedImage, 255, 255, 255);
            if ($white !== false) {
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $white);
            }
        }

        $resampled = imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        if (!$resampled) {
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            return false;
        }

        $result = $needsTransparency
            ? imagepng($resizedImage, $destinationPath, self::PNG_COMPRESSION)
            : imagejpeg($resizedImage, $destinationPath, self::JPEG_QUALITY);

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        return $result;
    }

    private function calculateNewDimensions(int $w, int $h): array {
        if ($w <= self::MAX_DIMENSION && $h <= self::MAX_DIMENSION) {
            return [max(1, $w), max(1, $h)];
        }
        $ratio = min(self::MAX_DIMENSION / max(1, $w), self::MAX_DIMENSION / max(1, $h));
        return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
    }
}
