<?php
/**
 * core/autoload.php
 * ─────────────────────────────────────────
 * কোনো থার্ড-পার্টি অটোলোডার (Composer ইত্যাদি) ছাড়াই ক্লাস লোড করে।
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $dirs = [
        DPS_ROOT . '/core/',
        DPS_ROOT . '/models/',
        DPS_ROOT . '/controllers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
