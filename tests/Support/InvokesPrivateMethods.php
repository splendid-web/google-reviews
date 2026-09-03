<?php

declare(strict_types=1);

namespace splendidweb\googlereviews\tests\Support;

use ReflectionMethod;

trait InvokesPrivateMethods
{
    /**
     * @param mixed ...$args
     */
    protected function invokePrivate(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }
}
