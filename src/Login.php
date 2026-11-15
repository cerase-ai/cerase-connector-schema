<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * How a sign-in made in the connector's own browser runs.
 *
 *     auth_login:
 *       args: ["--login"]   # the arguments the connector's image runs with
 *       port: 6080          # where it serves the page the person signs in on
 *
 * Only a connector whose `auth_kind` is `browser` declares it. The sign-in runs
 * in the connector's own image and is kept in the connector's state, so the
 * descriptor must declare both. An absent block declares nothing; a non-browser
 * connector must leave it absent.
 */
final class Login
{
    /** The descriptor key holding the declaration. */
    public const KEY = 'auth_login';

    /** The keys a declaration may carry. */
    private const KEYS = ['args', 'port'];

    /** The most arguments a sign-in may run with. */
    private const MAX_ARGS = 8;

    /** An image argument: letters, digits and _ . / : = @ % + - only. */
    private const ARG_REGEX = '~^[A-Za-z0-9_./:=@%+-]{1,100}$~';

    /** The lowest port a sign-in may serve its page on. */
    private const MIN_PORT = 1024;

    /** The highest port a sign-in may serve its page on. */
    private const MAX_PORT = 65535;

    /** The platform's own ports, which a sign-in may not take. */
    private const PLATFORM_PORTS = [3000, 3001];

    /**
     * Every reason the declaration cannot be read, one sentence each, naming
     * the offending key or argument. Empty when the declaration is valid.
     *
     * A connector that is not `browser` must leave the block absent. A
     * `browser` connector must declare it, in its own image, with the login
     * kept in its state.
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

        $login = $descriptor[self::KEY] ?? null;

        if (($descriptor['auth_kind'] ?? null) !== 'browser') {
            return $login === null ? [] : [$message('auth_login_not_browser')];
        }

        if ($login === null) {
            return [$message('auth_login_required')];
        }

        if (! is_array($login) || ($login !== [] && array_is_list($login))) {
            return [$message('auth_login_not_a_map')];
        }

        $found = [];

        foreach (array_keys($login) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                $found[] = $message('auth_login_key_unknown', [':key' => (string) $key]);
            }
        }

        $args = $login['args'] ?? null;
        if (! is_array($args)
            || $args === []
            || ! array_is_list($args)
            || count($args) > self::MAX_ARGS) {
            $found[] = $message('auth_login_args_invalid');
        } else {
            foreach ($args as $arg) {
                if (! is_string($arg) || preg_match(self::ARG_REGEX, $arg) !== 1) {
                    $found[] = $message('auth_login_arg_invalid', [':arg' => self::shown($arg)]);
                }
            }
        }

        // A port left null declares no viewer: the sign-in prints a QR code instead.
        if (($login['port'] ?? null) !== null && ! self::validPort($login['port'])) {
            $found[] = $message('auth_login_port_invalid');
        }

        if (! self::runsItsOwnImage($descriptor)) {
            $found[] = $message('auth_login_without_image');
        }

        $state = $descriptor['install_state'] ?? null;
        if (! is_array($state) || $state === []) {
            $found[] = $message('auth_login_without_state');
        }

        return array_values(array_unique($found));
    }

    /**
     * The declaration as the descriptor reads it: the arguments as a trimmed
     * list without blanks and the port as an integer. Null when neither an
     * argument nor a port is given (nothing declared).
     *
     * @return array{args: list<string>, port: int|null}|null
     */
    public static function normalize(mixed $login): ?array
    {
        if (! is_array($login)) {
            return null;
        }

        $args = $login['args'] ?? null;
        $args = is_array($args) ? $args : [];
        $normalized = [];
        foreach ($args as $arg) {
            if (! is_string($arg)) {
                continue;
            }
            $arg = trim($arg);
            if ($arg !== '') {
                $normalized[] = $arg;
            }
        }

        $port = $login['port'] ?? null;
        $portBlank = $port === null || (is_string($port) && trim($port) === '');
        if (is_string($port) && is_numeric(trim($port))) {
            $port = (int) trim($port);
        }

        if ($normalized === [] && $portBlank) {
            return null;
        }

        return [
            'args' => $normalized,
            'port' => $portBlank || ! is_int($port) ? null : $port,
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

        $image = $descriptor['install_image'] ?? null;

        return is_string($image) && trim($image) !== '';
    }

    private static function validPort(mixed $port): bool
    {
        return is_int($port)
            && $port >= self::MIN_PORT
            && $port <= self::MAX_PORT
            && ! in_array($port, self::PLATFORM_PORTS, true);
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
}
