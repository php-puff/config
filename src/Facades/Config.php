<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

use Puff\Di\Facade;

/**
 * Class Config
 *
 * @method static bool                 has(string $key)
 * @method static mixed                get(string|list<string> $key, mixed $default = null)
 * @method static \Puff\Config\Config  set(string|array<string, mixed> $key, mixed $value = null)
 * @method static array<string, mixed> all()
 */
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'config';
    }
}
