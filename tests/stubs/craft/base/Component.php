<?php

declare(strict_types=1);

namespace craft\base;

/**
 * Minimal stub for unit tests when craftcms/cms is not installed in this package.
 */
class Component
{
    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }
}
