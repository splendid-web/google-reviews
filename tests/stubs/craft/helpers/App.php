<?php

declare(strict_types=1);

namespace craft\helpers;

/**
 * Minimal stub for unit tests when craftcms/cms is not installed in this package.
 */
class App
{
    public static function parseEnv(?string $str): string|false|null
    {
        if ($str === null || $str === '') {
            return $str;
        }

        if (preg_match('/^\$(\w+)$/', $str, $matches) === 1) {
            $value = getenv($matches[1]);
            return $value === false ? $str : $value;
        }

        return $str;
    }
}
