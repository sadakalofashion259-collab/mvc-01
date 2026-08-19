<?php
declare(strict_types=1);

/**
 * StockImageService
 * ───────────────────────────
 * Handles saving stock entry photos inside the Stock module itself.
 * Storage root: Stock/Uploads/stock/<Month_Year>/
 * DB stores relative path: Stock/Uploads/stock/<Month_Year>/file.jpg
 */
class StockImageService {
    private string $uploadRoot;

    public function __construct(string $uploadRoot = '') {
        $this->uploadRoot = $uploadRoot !== '' ? $uploadRoot : __DIR__ . '/../Uploads/stock';
    }

    /**
     * Save a base64 webcam/data-URL image.
     * Returns relative web path (Stock/Uploads/stock/...) or null on failure.
     */
    public function saveBase64Image(string $dataUrl): ?string {
        if (!preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $dataUrl)) {
            return null;
        }
        $parts = explode(';base64,', $dataUrl);
        if (!isset($parts[1])) {
            return null;
        }
        $decoded = base64_decode((string)$parts[1], true);
        if ($decoded === false || $decoded === '') {
            return null;
        }
        $folder = $this->uploadRoot . '/' . date('F_Y') . '/';
        if (!is_dir($folder)) {
            if (!mkdir($folder, 0755, true) && !is_dir($folder)) {
                return null;
            }
        }
        $filename = 'stk_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $targetPath = $folder . $filename;
        if (!file_put_contents($targetPath, $decoded)) {
            return null;
        }
        return 'Stock/Uploads/stock/' . date('F_Y') . '/' . $filename;
    }
}
