<?php

declare(strict_types=1);

/**
 * Unit-test bootstrap.
 *
 * Works with either `composer install` or a standalone PHPUnit PHAR.
 * Loads lightweight Craft stubs when craftcms/cms is not present in vendor.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefixes = [
            'splendidweb\\googlereviews\\tests\\' => $root . '/tests/',
            'splendidweb\\googlereviews\\' => $root . '/src/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require $path;
            }
            return;
        }
    });
}

$stubRoot = __DIR__ . '/stubs';

spl_autoload_register(static function (string $class) use ($stubRoot): void {
    if (!str_starts_with($class, 'craft\\')) {
        return;
    }

    if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
        return;
    }

    $path = $stubRoot . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require $path;
    }
}, true, true);
