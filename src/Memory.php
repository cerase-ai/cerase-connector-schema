<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The memory a connector's container gets, in megabytes.
 *
 *     install_memory_mb: 512         # for its runner and for its browser sign-in
 *
 * Absent means the appliance's default, 512 MB today. Only a connector that
 * runs a container declares it: a connector reached at its own address runs no
 * container and so declares no memory.
 */
final class Memory
{
    /** The descriptor key holding the declaration. */
    public const KEY = 'install_memory_mb';

    /** The smallest amount a connector may declare, in megabytes. */
    public const MIN = 256;

    /** The largest amount a connector may declare, in megabytes. */
    public const MAX = 4096;

    /**
     * Every reason the declaration cannot be read, one sentence each. Empty
     * when the declaration is valid.
     *
     * A connector that declares no memory (null or absent) is always valid.
     *
     * @param  array<string, mixed>  $descriptor
     * @return list<string>
     */
    public static function violations(array $descriptor, string $locale = 'en'): array
    {
        $labels = Labels::for($locale);
        $message = static function (string $code, array $replace = []) use ($labels): string {
            $template = $labels['violation.'.$code] ?? $code;

            return strtr((string) $template, $replace);
        };

        $memory = $descriptor[self::KEY] ?? null;

        if ($memory === null) {
            return [];
        }

        $found = [];

        if (! Hosts::runsARunner($descriptor)) {
            $found[] = $message('memory_without_runner');
        }

        if (! is_int($memory) || $memory < self::MIN || $memory > self::MAX) {
            $found[] = $message('memory_mb_invalid');
        }

        return array_values(array_unique($found));
    }

    /**
     * The declaration as the descriptor reads it: a whole number of megabytes.
     *
     * Accepts what a form hands over as well as a YAML or JSON file. Null when
     * the value is absent, blank or not a whole number.
     */
    public static function normalize(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('~^[+-]?\d+$~', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
