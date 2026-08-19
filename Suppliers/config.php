<?php
declare(strict_types=1);

/**
 * Central bootstrap for the Suppliers module.
 * এই ফাইল সরাসরি ব্রাউজার থেকে খোলা যাবে না — শুধু entry ফাইল (suppliers.php,
 * supplier_profile.php, Sms/index.php) থেকে include হবে।
 */
if (!defined('SK_APP')) {
    http_response_code(403);
    exit('403 Forbidden');
}

/* ---- Application paths ---- */
define('SK_BASE', __DIR__);            // public_html/Suppliers
define('SK_ROOT', dirname(__DIR__));   // public_html
define('SK_LOGS', SK_BASE . '/Logs');
define('SK_IMG',  SK_BASE . '/Img');
define('SK_ENV_PATH', '/home/sadakalo/App/.env');

date_default_timezone_set('Asia/Dhaka');

/* ---- Secret vault (.env) reader ---- */
function sk_env(string $key, string $default = ''): string {
    static $vault = null;
    if ($vault === null) {
        $vault = (is_readable(SK_ENV_PATH))
            ? (parse_ini_file(SK_ENV_PATH, false, INI_SCANNER_RAW) ?: [])
            : [];
    }
    return (isset($vault[$key]) && is_scalar($vault[$key])) ? (string)$vault[$key] : $default;
}

if (!is_readable(SK_ENV_PATH)) {
    http_response_code(500);
    exit('Security vault not found. Contact administrator.');
}

/* ---- Card encryption key — এখন .env থেকে (হার্ডকোড নয়) ---- */
if (!defined('CARD_ENC_KEY')) {
    define('CARD_ENC_KEY', sk_env('CARD_ENC_KEY', ''));
}

/* ---- Simple class autoloader ---- */
spl_autoload_register(static function (string $class): void {
    static $map = null;
    if ($map === null) {
        $map = [
            'Logger'                 => SK_BASE . '/Helper/Logger.php',
            'Database'               => SK_BASE . '/Helper/Database.php',
            'Security'               => SK_BASE . '/Helper/Security.php',
            'ImageUploader'          => SK_BASE . '/Helper/ImageUploader.php',
            'Audit'                  => SK_BASE . '/Helper/Audit.php',
            'SupplierModelInterface' => SK_BASE . '/Models/SupplierModelInterface.php',
            'SupplierModel'          => SK_BASE . '/Models/SupplierModel.php',
            'SupplierController'     => SK_BASE . '/Controllers/SupplierController.php',
            'SmsGateway'             => SK_BASE . '/Sms/SmsGateway.php',
            'SmsService'             => SK_BASE . '/Sms/SmsService.php',
        ];
    }
    if (isset($map[$class]) && is_file($map[$class])) {
        require_once $map[$class];
    }
});
