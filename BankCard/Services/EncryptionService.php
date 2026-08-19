<?php
declare(strict_types=1);

/**
 * Card number & PIN encryption (AES-256-CBC)
 * Key comes from CARD_ENC_KEY defined in db_connect / .env
 */
final class EncryptionService
{
    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }
        $key    = hash('sha256', CARD_ENC_KEY, true);
        $iv     = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encText): string
    {
        if ($encText === '') {
            return '';
        }
        try {
            $key  = hash('sha256', CARD_ENC_KEY, true);
            $data = base64_decode($encText);
            if ($data === false || strlen($data) < 16) {
                return '';
            }
            $iv     = substr($data, 0, 16);
            $cipher = substr($data, 16);
            $plain  = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $plain !== false ? $plain : '';
        } catch (Throwable $e) {
            error_log('Decrypt Error: ' . $e->getMessage());
            return '';
        }
    }
}
