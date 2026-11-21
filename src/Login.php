<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * How a sign-in made in the connector's own browser runs.
 *
 *     auth_login:
 *       args: ["--login"]   # the arguments the connector's image runs with
 *       port: null          # where it serves the page, or null when it prints a code
 *       steps:              # the steps a person follows, per language
 *         it: ["Apri l'app Esempio.", "Inquadra il codice qui sotto."]
 *         en: ["Open the Esempio app.", "Scan the code below."]
 *       code_seconds: 160   # how long a code it prints lasts
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
    private const KEYS = ['args', 'port', 'steps', 'code_seconds'];

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

    /** The languages a steps block may be written in. */
    private const STEP_LANGUAGES = Labels::LOCALES;

    /** The language a steps block always declares, since the console shows it. */
    private const REQUIRED_STEPS_LANGUAGE = 'it';

    /** The most steps a language may hold. */
    private const MAX_STEPS = 8;

    /** The shortest step a person can follow. */
    private const MIN_STEP_LENGTH = 1;

    /** The longest step a person can follow. */
    private const MAX_STEP_LENGTH = 200;

    /** The shortest lifetime a printed code may have, in seconds. */
    private const MIN_CODE_SECONDS = 10;

    /** The longest lifetime a printed code may have, in seconds. */
    private const MAX_CODE_SECONDS = 900;

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

        foreach (self::stepDefects($login['steps'] ?? null) as $defect) {
            $found[] = $message($defect['code'], $defect['replace']);
        }

        // A code is printed only by a sign-in with no viewer port.
        $codeSeconds = $login['code_seconds'] ?? null;
        if ($codeSeconds !== null) {
            if (($login['port'] ?? null) !== null) {
                $found[] = $message('auth_login_code_seconds_with_port');
            } elseif (! self::validCodeSeconds($codeSeconds)) {
                $found[] = $message('auth_login_code_seconds_invalid');
            }
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
     * list without blanks and the port as an integer. Steps are a trimmed list
     * per language, and the code lifetime an integer. Null when neither an
     * argument nor a port is given (nothing declared).
     *
     * @return array{args: list<string>, port: int|null, steps?: array<string, list<string>>, code_seconds?: int}|null
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

        $result = [
            'args' => $normalized,
            'port' => $portBlank || ! is_int($port) ? null : $port,
        ];

        $steps = self::normalizeSteps($login['steps'] ?? null);
        if ($steps !== []) {
            $result['steps'] = $steps;
        }

        $codeSeconds = self::normalizeCodeSeconds($login['code_seconds'] ?? null);
        if ($codeSeconds !== null) {
            $result['code_seconds'] = $codeSeconds;
        }

        return $result;
    }

    /**
     * The steps to sign in, in the requested language: the locale's own when
     * declared, Italian otherwise. Empty when the login declares none or the
     * declaration cannot be read.
     *
     * @return list<string>
     */
    public static function steps(mixed $login, string $locale): array
    {
        $normalized = self::normalize($login);

        if ($normalized === null) {
            return [];
        }

        $steps = $normalized['steps'] ?? null;

        if (! is_array($steps) || self::stepDefects($steps) !== []) {
            return [];
        }

        $list = $steps[$locale] ?? $steps[self::REQUIRED_STEPS_LANGUAGE] ?? null;

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * How long a printed code lasts, in seconds. Null unless the login declares
     * a whole number from 10 to 900 with no viewer port.
     */
    public static function codeSeconds(mixed $login): ?int
    {
        $normalized = self::normalize($login);

        if ($normalized === null || ($normalized['port'] ?? null) !== null) {
            return null;
        }

        $seconds = $normalized['code_seconds'] ?? null;

        return self::validCodeSeconds($seconds) ? $seconds : null;
    }

    /**
     * The steps block as the form reads it: each language's lines trimmed, with
     * blanks dropped and a language dropped when it holds no step.
     *
     * @return array<string, list<string>>
     */
    private static function normalizeSteps(mixed $steps): array
    {
        if (! is_array($steps)) {
            return [];
        }

        $normalized = [];

        foreach ($steps as $language => $value) {
            $lines = is_array($value)
                ? array_values($value)
                : (is_string($value) ? preg_split('/\R/u', $value) : []);

            if (! is_array($lines)) {
                continue;
            }

            $list = [];
            foreach ($lines as $line) {
                if (! is_string($line)) {
                    continue;
                }
                $line = trim($line);
                if ($line !== '') {
                    $list[] = $line;
                }
            }

            if ($list !== []) {
                $normalized[(string) $language] = $list;
            }
        }

        return $normalized;
    }

    /**
     * A code lifetime as the form reads it: an integer, or a digit string
     * turned into one. Anything else declares nothing.
     */
    private static function normalizeCodeSeconds(mixed $seconds): ?int
    {
        if (is_int($seconds)) {
            return $seconds;
        }

        if (is_string($seconds) && preg_match('/^\d+$/', trim($seconds)) === 1) {
            return (int) trim($seconds);
        }

        return null;
    }

    /**
     * Every defect in a steps block, as the code of its sentence and the values
     * that sentence names. Empty when the block is valid.
     *
     * @return list<array{code: string, replace: array<string, string>}>
     */
    private static function stepDefects(mixed $steps): array
    {
        if ($steps === null) {
            return [];
        }

        if (! is_array($steps) || $steps === [] || array_is_list($steps)) {
            return [['code' => 'auth_login_steps_not_a_map', 'replace' => []]];
        }

        $defects = [];

        if (! array_key_exists(self::REQUIRED_STEPS_LANGUAGE, $steps)) {
            $defects[] = ['code' => 'auth_login_steps_it_required', 'replace' => []];
        }

        foreach ($steps as $language => $list) {
            $language = (string) $language;

            if (! in_array($language, self::STEP_LANGUAGES, true)) {
                $defects[] = [
                    'code' => 'auth_login_steps_language_unknown',
                    'replace' => [':language' => $language],
                ];
                continue;
            }

            if (! is_array($list)
                || $list === []
                || ! array_is_list($list)
                || count($list) > self::MAX_STEPS) {
                $defects[] = [
                    'code' => 'auth_login_steps_invalid',
                    'replace' => [':language' => $language],
                ];
                continue;
            }

            foreach ($list as $position => $step) {
                if (! self::validStep($step)) {
                    $defects[] = [
                        'code' => 'auth_login_step_invalid',
                        'replace' => [
                            ':language' => $language,
                            ':position' => (string) ($position + 1),
                        ],
                    ];
                }
            }
        }

        return $defects;
    }

    /**
     * A step is one line of plain text: a non-blank string of 1 to 200
     * characters, valid UTF-8, with no < and no control character.
     */
    private static function validStep(mixed $step): bool
    {
        if (! is_string($step) || ! mb_check_encoding($step, 'UTF-8')) {
            return false;
        }

        $length = mb_strlen(trim($step));

        return $length >= self::MIN_STEP_LENGTH
            && $length <= self::MAX_STEP_LENGTH
            && preg_match('/[<\p{Cc}]/u', $step) !== 1;
    }

    private static function validCodeSeconds(mixed $seconds): bool
    {
        return is_int($seconds)
            && $seconds >= self::MIN_CODE_SECONDS
            && $seconds <= self::MAX_CODE_SECONDS;
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
