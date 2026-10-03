<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * Format rules for the connector install descriptor — the security boundary of
 * the whole schema.
 *
 * `COMMAND_REGEX` guards the stdio install command: it is served to the
 * sandboxed runner, so no quoting, pipes, redirection, substitution or
 * chaining may pass. `IMAGE_REGEX` guards the OCI image reference so nothing
 * can break out of the docker argument. Both literals are byte-identical to
 * the ones the marketplace publisher form enforces; they live here so the two
 * apps share ONE pattern instead of two copies that can drift.
 */
final class Rules
{
    /**
     * Install command: letters, digits, spaces and - _ . / : @ % + only.
     */
    public const COMMAND_REGEX = '~^[A-Za-z0-9 \-_./:@%+]+$~';

    /**
     * OCI image reference: letters, digits and . _ / : @ - + only (no spaces).
     */
    public const IMAGE_REGEX = '~^[A-Za-z0-9._/:@\-+]+$~';

    /**
     * Cross-field violation: `credential_delivery` is `env` but no
     * `credential_env` (the env-var NAME) was given — the per-connection runner
     * is spawned fail-closed and can never receive its credential.
     */
    public const VIOLATION_CREDENTIAL_ENV_REQUIRED = 'credential_env_required';

    /**
     * Cross-field violation: `auth_kind` is `oauth2` but no `auth_provider` slug
     * was given — an OAuth connector with no provider cannot resolve its OAuth
     * app / discovery, so it can never complete a connection.
     */
    public const VIOLATION_AUTH_PROVIDER_REQUIRED = 'auth_provider_required';

    /**
     * A connector authenticating with a key declares no `credential_parts`, so
     * the field the person types has nowhere to say where its value is found.
     */
    public const VIOLATION_CREDENTIAL_FIELDS_REQUIRED = 'credential_fields_required';

    /** A field's key is blank or is not a valid variable name. */
    public const VIOLATION_FIELD_KEY_INVALID = 'field_key_invalid';

    /** Two fields of one list share a key. */
    public const VIOLATION_FIELD_KEY_DUPLICATE = 'field_key_duplicate';

    /** A field has no label. */
    public const VIOLATION_FIELD_LABEL_REQUIRED = 'field_label_required';

    /** A field has no sentence saying where its value is found. */
    public const VIOLATION_FIELD_HELP_REQUIRED = 'field_help_required';

    /** `secret` or `optional` is not a boolean. */
    public const VIOLATION_FIELD_FLAG_INVALID = 'field_flag_invalid';

    /** A field's `suggest` is malformed; the detail says how. */
    public const VIOLATION_FIELD_SUGGESTION_INVALID = 'field_suggestion_invalid';

    /** A field of `provider_fields` declares a suggestion, which only an account's field may. */
    public const VIOLATION_FIELD_SUGGESTION_NOT_ALLOWED = 'field_suggestion_not_allowed';

    /**
     * The Laravel validation rule string for the install command.
     */
    public static function commandRule(): string
    {
        return 'regex:'.self::COMMAND_REGEX;
    }

    /**
     * The Laravel validation rule string for the install image reference.
     */
    public static function imageRule(): string
    {
        return 'regex:'.self::IMAGE_REGEX;
    }

    /**
     * Whether the given string is an acceptable install command.
     */
    public static function commandIsValid(string $command): bool
    {
        return preg_match(self::COMMAND_REGEX, $command) === 1;
    }

    /**
     * Whether the given string is an acceptable OCI image reference.
     */
    public static function imageIsValid(string $image): bool
    {
        return preg_match(self::IMAGE_REGEX, $image) === 1;
    }

    /**
     * The strict cross-field descriptor rules, as a PURE function.
     *
     * These are the SAME two rules the Filament forms enforce via `->required()`
     * when {@see ConnectorSchemaConfig::strictCrossField()} is on. Exposing them
     * here lets the SERVER side (the control-plane's custom-connector registrar /
     * API-maintainer path) reject the identical set — one definition, so the
     * form and the API can never drift.
     *
     * Returns the violated rule codes (an empty list = the descriptor is
     * well-formed against the strict rules). A blank string counts as missing:
     *
     *   - {@see VIOLATION_CREDENTIAL_ENV_REQUIRED}: `credential_delivery` is
     *     `env` but `credential_env` is blank;
     *   - {@see VIOLATION_AUTH_PROVIDER_REQUIRED}: `auth_kind` is `oauth2` but
     *     `auth_provider` is blank.
     *
     * @param  array<string, mixed>  $descriptor
     * @return list<string>
     */
    public static function crossFieldViolations(array $descriptor): array
    {
        $violations = [];

        if (($descriptor['credential_delivery'] ?? null) === 'env'
            && self::isBlank($descriptor['credential_env'] ?? null)) {
            $violations[] = self::VIOLATION_CREDENTIAL_ENV_REQUIRED;
        }

        if (($descriptor['auth_kind'] ?? null) === 'oauth2'
            && self::isBlank($descriptor['auth_provider'] ?? null)) {
            $violations[] = self::VIOLATION_AUTH_PROVIDER_REQUIRED;
        }

        return $violations;
    }

    /**
     * The rules every declared field obeys, as a PURE function.
     *
     * Every field of `credential_parts` and `provider_fields` has a key, a
     * label and one sentence (`help`) saying where its value is found in the
     * product the connector talks to; a connector that authenticates with a key
     * declares the field the person types; a suggestion names only fields of
     * the same list that are not secret. See {@see CredentialFields}.
     *
     * Each violation names the list and the field key it is about, and carries
     * a detail for the suggestion rule; {@see fieldViolationMessage()} turns
     * one into a sentence. An empty list means the fields are well declared.
     *
     * @param  array<string, mixed>  $descriptor  needs `auth_kind`, `credential_parts`, `provider_fields`
     * @return list<array{code: string, list: string, key: string, detail: string}>
     */
    public static function fieldViolations(array $descriptor): array
    {
        $violations = [];
        $add = static function (string $code, string $list, string $key, string $detail = '') use (&$violations): void {
            $violations[] = ['code' => $code, 'list' => $list, 'key' => $key, 'detail' => $detail];
        };

        $parts = $descriptor['credential_parts'] ?? null;
        if (($descriptor['auth_kind'] ?? null) === 'bearer' && (! is_array($parts) || $parts === [])) {
            $add(self::VIOLATION_CREDENTIAL_FIELDS_REQUIRED, 'credential_parts', '');
        }

        foreach (CredentialFields::LISTS as $list) {
            $fields = $descriptor[$list] ?? null;
            if (! is_array($fields) || $fields === []) {
                continue;
            }
            $fields = array_values($fields);

            // What a suggestion may name: the other fields of this list that
            // are declared not secret.
            $plain = [];
            foreach ($fields as $field) {
                if (is_array($field) && ($field['secret'] ?? true) === false) {
                    $plain[trim((string) ($field['key'] ?? ''))] = true;
                }
            }

            $seen = [];
            foreach ($fields as $i => $field) {
                if (! is_array($field)) {
                    $add(self::VIOLATION_FIELD_KEY_INVALID, $list, '#'.($i + 1));

                    continue;
                }
                $key = trim((string) ($field['key'] ?? ''));
                $name = $key !== '' ? $key : '#'.($i + 1);
                if (preg_match(CredentialFields::KEY_REGEX, $key) !== 1) {
                    $add(self::VIOLATION_FIELD_KEY_INVALID, $list, $name);
                } elseif (isset($seen[$key])) {
                    $add(self::VIOLATION_FIELD_KEY_DUPLICATE, $list, $name);
                }
                $seen[$key] = true;
                if (self::isBlank($field['label'] ?? null)) {
                    $add(self::VIOLATION_FIELD_LABEL_REQUIRED, $list, $name);
                }
                if (self::isBlank($field['help'] ?? null)) {
                    $add(self::VIOLATION_FIELD_HELP_REQUIRED, $list, $name);
                }
                foreach (['secret', 'optional'] as $flag) {
                    if (array_key_exists($flag, $field) && ! is_bool($field[$flag])) {
                        $add(self::VIOLATION_FIELD_FLAG_INVALID, $list, $name, $flag);
                    }
                }

                if (! array_key_exists('suggest', $field) || $field['suggest'] === null) {
                    continue;
                }
                if ($list !== 'credential_parts') {
                    $add(self::VIOLATION_FIELD_SUGGESTION_NOT_ALLOWED, $list, $name);

                    continue;
                }
                $problem = self::suggestionProblem($field['suggest'], $key, $plain);
                if ($problem !== null) {
                    $add(self::VIOLATION_FIELD_SUGGESTION_INVALID, $list, $name, $problem);
                }
            }
        }

        return $violations;
    }

    /**
     * One violation from {@see fieldViolations()} as a sentence, in English or
     * Italian.
     *
     * @param  array{code: string, list: string, key: string, detail: string}  $violation
     */
    public static function fieldViolationMessage(array $violation, string $locale = 'en'): string
    {
        $labels = Labels::for($locale);
        $template = $labels['violation.'.$violation['code']] ?? $violation['code'];

        return strtr((string) $template, [
            ':list' => $violation['list'],
            ':key' => $violation['key'],
            ':detail' => $violation['detail'],
        ]);
    }

    /**
     * What is wrong with a declared suggestion, or null when nothing is.
     *
     * @param  array<string, bool>  $plain  the keys of this list's fields declared not secret
     */
    private static function suggestionProblem(mixed $raw, string $self, array $plain): ?string
    {
        if (! is_array($raw)) {
            return 'it must be a mapping of method, url, body and value_at';
        }
        $unknown = array_diff(array_keys($raw), CredentialFields::SUGGESTION_KEYS);
        if ($unknown !== []) {
            return 'unknown key(s) '.implode(', ', $unknown).'; allowed: '.implode(', ', CredentialFields::SUGGESTION_KEYS);
        }
        $suggest = CredentialFields::normalizeSuggestion($raw);
        if ($suggest === null) {
            return 'url is required';
        }
        if (! in_array($suggest['method'], CredentialFields::SUGGESTION_METHODS, true)) {
            return 'method must be GET or POST';
        }
        $url = (string) $suggest['url'];
        if (! str_starts_with($url, '{') && ! preg_match('~^https?://~i', $url)) {
            return 'url must start with http(s):// or with a {FIELD}';
        }
        if (array_key_exists('body', $suggest)) {
            if ($suggest['method'] !== 'POST') {
                return 'a body is sent only with POST';
            }
            if (! is_array($suggest['body'])) {
                return 'body must be a JSON object or array';
            }
        }
        if (preg_match(CredentialFields::VALUE_AT_REGEX, (string) $suggest['value_at']) !== 1) {
            return 'value_at must be a dot path such as result or data.*.name';
        }
        $names = CredentialFields::placeholdersOf($suggest);
        if ($names === []) {
            return 'it names no field: the request is built from the fields already typed, as {FIELD}';
        }
        foreach ($names as $name) {
            if ($name === $self) {
                return "it names its own field {{$name}}";
            }
            if (! isset($plain[$name])) {
                return "{{$name}} is not a field of this connector declared secret: false";
            }
        }

        return null;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
