<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config;

final class Environment
{
    /** @param array<string, string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $file): self
    {
        if (!\is_file($file)) {
            return new self([]);
        }
        $content = \file_get_contents($file);
        if ($content === false) {
            throw new ConfigException("Unable to read environment file [{$file}].");
        }
        return new self((new EnvParser())->parse($content));
    }

    public function apply(bool $immutable = true): self
    {
        foreach ($this->values as $key => $value) {
            $existing = \getenv($key);
            if ($immutable && $existing !== false) {
                continue;
            }

            if (!\putenv("{$key}={$value}")) {
                throw new ConfigException("Unable to set environment variable [{$key}].");
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $effective = [];
        $environment = \getenv();
        foreach ($environment as $key => $value) {
            $effective[(string) $key] = (string) $value;
        }
        return new self($effective);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }
}
