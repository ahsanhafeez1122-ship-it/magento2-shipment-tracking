<?php
declare(strict_types=1);

/**
 * Carrier matching, URL building, statuses and webhook signing are plain PHP,
 * so their tests run without a Magento install.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'AhsanHafeez\\ShipmentTracking\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
