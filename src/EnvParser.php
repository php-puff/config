<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config;

/** @internal */
final class EnvParser
{
    /** @return array<string, string> */
    public function parse(string $content): array
    {
        $values = [];
        $lines = \preg_split('/\R/', \ltrim($content, "\xEF\xBB\xBF")) ?: [];

        foreach ($lines as $index => $line) {
            $line = \trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (\str_starts_with($line, 'export ')) {
                $line = \ltrim(\substr($line, 7));
            }

            if (!\preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new ConfigException('Invalid environment entry on line ' . ($index + 1) . '.');
            }

            $values[$matches[1]] = $this->value($matches[2], $values, $index + 1);
        }

        return $values;
    }

    /** @param array<string, string> $values */
    private function value(string $value, array $values, int $line): string
    {
        $value = \trim($value);
        if ($value === '') {
            return '';
        }

        if ($value[0] === "'") {
            if (!\preg_match("/^'([^']*)'\\s*(?:#.*)?$/s", $value, $matches)) {
                throw new ConfigException("Invalid single-quoted value on line {$line}.");
            }
            return $matches[1];
        }

        if ($value[0] === '"') {
            if (!\preg_match('/^"((?:\\\\.|[^"\\\\])*)"\s*(?:#.*)?$/s', $value, $matches)) {
                throw new ConfigException("Invalid double-quoted value on line {$line}.");
            }
            $value = \stripcslashes($matches[1]);
        } else {
            $value = \preg_replace('/\s+#.*$/', '', $value) ?? $value;
            $value = \trim($value);
        }

        return (string) \preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $match) use ($values): string {
            if (\array_key_exists($match[1], $values)) {
                return $values[$match[1]];
            }
            $existing = \getenv($match[1]);
            return $existing === false ? '' : $existing;
        }, $value);
    }
}
