<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config;

final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /** @param array<string, mixed> $items */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function load(string $basePath, string ...$paths): self
    {
        $files = [];
        foreach ($paths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (\is_dir($path)) {
                $file = \rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config.php';
                if (\is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        $items = [];
        foreach (\array_unique($files) as $file) {
            $loaded = require $file;
            if (\is_array($loaded)) {
                $items = \array_replace_recursive($items, $loaded);
            }
        }

        $config = new self($items);
        $environment = Environment::load(\rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env')->apply();
        $config->applyEnvironment($environment);
        return $config;
    }

    public static function env(string $value, mixed $current = null): mixed
    {
        if (\is_bool($current)) {
            return match (\strtolower($value)) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => (bool) $value,
            };
        }
        if (\is_int($current)) {
            return (int) $value;
        }
        if (\is_float($current)) {
            return (float) $value;
        }
        if ($current === null && \strtolower($value) === 'null') {
            return null;
        }
        return $value;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    /**
     * @param  string|list<string>                            $key
     * @return ($key is array ? array<string, mixed> : mixed)
     */
    public function get(string|array $key, mixed $default = null): mixed
    {
        if (\is_array($key)) {
            $result = [];
            foreach ($key as $name) {
                $result[$name] = $this->get($name, $default);
            }
            return $result;
        }
        $value = $this->items;
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @param string|array<string, mixed> $key */
    public function set(string|array $key, mixed $value = null): self
    {
        $items = $this->items;
        foreach (\is_array($key) ? $key : [$key => $value] as $path => $item) {
            $this->setPath($items, (string) $path, $item);
        }
        $this->items = $items;
        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /** @param array<string, mixed> $items */
    private function setPath(array &$items, string $path, mixed $value): void
    {
        $segments = \explode('.', $path);
        $last = \array_pop($segments);
        $target = &$items;
        foreach ($segments as $segment) {
            if (\array_key_exists($segment, $target) && !\is_array($target[$segment])) {
                throw new ConfigException("Unable to set configuration [{$path}]: [{$segment}] is not an array.");
            }
            if (!\array_key_exists($segment, $target)) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target[(string) $last] = $value;
    }

    private function applyEnvironment(Environment $environment): void
    {
        foreach ($environment->all() as $key => $value) {
            if (!\str_contains($key, '__')) {
                continue;
            }
            $path = \strtolower(\str_replace('__', '.', $key));
            if ($this->has($path)) {
                $this->set($path, self::env($value, $this->get($path)));
            }
        }
    }
}
