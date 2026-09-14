<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

use Puff\Config\Config;
use Puff\Di\Container;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = \getenv($key);
        if ($value === false) {
            return $default;
        }
        return Puff\Config\Config::env($value, $default);
    }
}

if (!\function_exists('config')) {
    /** @param string|array<string, mixed>|null $key */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $container = Container::getInstance();
        if ($container === null) {
            throw new LogicException('The Puff container has not been initialized.');
        }

        $config = $container->get(Config::class);
        if ($key === null) {
            return $config;
        }
        return \is_array($key) ? $config->set($key) : $config->get($key, $default);
    }
}
