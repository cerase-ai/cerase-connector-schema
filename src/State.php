<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The one directory a connector keeps across a recreated container.
 *
 *     install_state:               # what the connector keeps, never how
 *       path: /home/node/.notes    # one absolute directory in the image
 *       max_mb: 512                # the most it may grow, in megabytes
 *       keep_running: true         # the server stays up between calls
 *
 * Only a connector that runs its own image declares it: a connector reached at
 * its own address or run from a command has no container of its own. The state
 * belongs to one connected account, so the connector must run one runner per
 * connection (delivery by environment variable or file) or make its login in
 * its own browser. The declaration says what the state is; the platform keeps
 * it.
 */
final class State
{
    /** The descriptor key holding the declaration. */
    public const KEY = 'install_state';

    /** The largest directory a connector may declare, in megabytes. */
    public const MAX_MB = 2048;

    /** The keys a declaration may hold. */
    private const KEYS = ['path', 'max_mb', 'keep_running'];

    /** Directories the platform owns; a connector keeps its state outside them. */
    private const FORBIDDEN = [
        '/proc',
        '/sys',
        '/dev',
        '/etc',
        '/usr',
        '/bin',
        '/sbin',
        '/lib',
        '/lib32',
        '/lib64',
        '/libx32',
        '/boot',
        '/run',
        '/var/run',
        '/tmp',
        '/opt/cerase',
    ];

    /** One path segment: letters, digits, dot, dash and underscore. */
    private const SEGMENT_REGEX = '~^[A-Za-z0-9._-]+$~';

    /**
     * Every reason the declaration cannot be read, one sentence each, naming
     * the offending key or path. Empty when the declaration is valid.
     *
     * A connector that declares no state (null or absent) is always valid.
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

        $state = $descriptor[self::KEY] ?? null;

        if ($state === null) {
            return [];
        }

        if (! is_array($state) || ($state !== [] && array_is_list($state))) {
            return [$message('state_not_a_map')];
        }

        $found = [];

        if (! self::runsItsOwnImage($descriptor)) {
            $found[] = $message('state_without_image');
        }
        if (! self::runsOneRunnerPerConnection($descriptor)) {
            $found[] = $message('state_not_per_connection');
        }

        foreach (array_keys($state) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                $found[] = $message('state_key_unknown', [':key' => (string) $key]);
            }
        }

        if (! array_key_exists('path', $state)) {
            $found[] = $message('state_path_missing');
        } elseif (! is_string($state['path']) || ! self::validPath($state['path'])) {
            $found[] = $message('state_path_invalid', [':path' => self::shown($state['path'])]);
        }

        if (! array_key_exists('max_mb', $state)
            || ! is_int($state['max_mb'])
            || $state['max_mb'] < 1
            || $state['max_mb'] > self::MAX_MB) {
            $found[] = $message('state_max_mb_invalid');
        }

        if (array_key_exists('keep_running', $state) && ! is_bool($state['keep_running'])) {
            $found[] = $message('state_keep_running_invalid');
        }

        return array_values(array_unique($found));
    }

    /**
     * The declaration as the descriptor reads it: a trimmed path, an integer
     * size and a boolean for whether the server stays running.
     *
     * Accepts what a form hands over as well as a YAML or JSON file. Null when
     * neither a path nor a size is given (nothing declared).
     *
     * @return array{path: string, max_mb: int, keep_running: bool}|null
     */
    public static function normalize(mixed $state): ?array
    {
        if (! is_array($state)) {
            return null;
        }

        $path = $state['path'] ?? null;
        $path = is_string($path) ? trim($path) : '';

        $max = $state['max_mb'] ?? null;
        $maxBlank = $max === null || (is_string($max) && trim($max) === '');

        if ($path === '' && $maxBlank) {
            return null;
        }

        if (is_string($max) && is_numeric(trim($max))) {
            $max = (int) trim($max);
        }

        return [
            'path' => $path,
            'max_mb' => is_int($max) ? $max : 0,
            'keep_running' => ($state['keep_running'] ?? false) === true,
        ];
    }

    /**
     * Whether the descriptor runs the connector in its own image.
     *
     * The mode decides; a descriptor that states no mode falls back to the
     * image it carries. A command or remote connector that also carries an
     * image is still not an image connector.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private static function runsItsOwnImage(array $descriptor): bool
    {
        $mode = $descriptor['install_mode'] ?? null;
        if (is_string($mode) && $mode !== '') {
            return $mode === 'image';
        }

        return self::hasText($descriptor['install_image'] ?? null);
    }

    /**
     * Whether the connector runs one runner per connected account: delivery
     * by environment variable or file, or a login made in its own browser.
     *
     * @param  array<string, mixed>  $descriptor
     */
    private static function runsOneRunnerPerConnection(array $descriptor): bool
    {
        if (($descriptor['auth_kind'] ?? null) === 'browser') {
            return true;
        }

        return in_array($descriptor['credential_delivery'] ?? null, ['env', 'file'], true);
    }

    private static function validPath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        if (strlen($path) > 200) {
            return false;
        }
        if ($path === '/' || str_ends_with($path, '/') || str_contains($path, '//')) {
            return false;
        }

        foreach (explode('/', substr($path, 1)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
            if (preg_match(self::SEGMENT_REGEX, $segment) !== 1) {
                return false;
            }
        }

        foreach (self::FORBIDDEN as $directory) {
            if ($path === $directory || str_starts_with($path, $directory.'/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * A value as a sentence shows it.
     */
    private static function shown(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : get_debug_type($value);
    }

    private static function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
