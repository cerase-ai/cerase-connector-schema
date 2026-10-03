<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The fields a connector asks for, as a descriptor declares them.
 *
 * Two lists share one shape:
 *
 *   - `credential_parts` — the values one account is made of, typed on the
 *     connect form (an instance URL, a login, an API key);
 *   - `provider_fields` — the values of the connector's OAuth app beyond its
 *     client id and secret, typed once by an administrator (a developer
 *     token).
 *
 * Each field is `{key, label, help, secret, optional}`. `help` is the one
 * sentence telling the person where that value is found in the product the
 * connector talks to; every form that shows the field shows it under it.
 *
 * A field of `credential_parts` may also carry `suggest`: a request the
 * platform makes once the fields it names are typed, and where in the JSON
 * answer the value sits.
 *
 *     suggest:
 *       method: POST                          # GET (default) or POST
 *       url: "{INSTANCE_URL}/api/list"        # names other fields as {KEY}
 *       body: {jsonrpc: "2.0", params: {}}    # POST only; strings may name fields
 *       value_at: result                      # dot path; `*` walks a list
 *
 * A placeholder may name only a field of the same list that is declared
 * `secret: false`: a secret never leaves the form to build a suggestion. The
 * answer at `value_at` is a string (one value) or a list of strings (several).
 */
final class CredentialFields
{
    /** The two lists a descriptor declares fields in. */
    public const LISTS = ['credential_parts', 'provider_fields'];

    /** The methods a suggestion may use. */
    public const SUGGESTION_METHODS = ['GET', 'POST'];

    /** The keys a suggestion may carry. */
    public const SUGGESTION_KEYS = ['method', 'url', 'body', 'value_at'];

    /**
     * A field key: it becomes an environment variable name and a `{KEY}`
     * placeholder, so it is one.
     */
    public const KEY_REGEX = '~^[A-Za-z_][A-Za-z0-9_]*$~';

    /** `{KEY}` inside a suggestion's URL or body. */
    public const PLACEHOLDER_REGEX = '~\{([A-Za-z_][A-Za-z0-9_]*)\}~';

    /** A dot path into the answer: names, list indexes and `*`. */
    public const VALUE_AT_REGEX = '~^(\*|[A-Za-z0-9_-]+)(\.(\*|[A-Za-z0-9_-]+))*$~';

    /**
     * The list as it is stored: a plain list, each field trimmed, its two flags
     * booleans, and a suggestion only where one was declared.
     *
     * Accepts what a form hands over as well as what a YAML or JSON file
     * holds: keyed items (a repeater's), a body still written as JSON text, a
     * suggestion with nothing filled in. Anything that is not a list of
     * fields comes back as null, which is what "declares no fields" is.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function normalize(mixed $fields): ?array
    {
        if (! is_array($fields) || $fields === []) {
            return null;
        }

        $out = [];
        foreach (array_values($fields) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $normalized = [
                'key' => trim((string) ($field['key'] ?? '')),
                'label' => trim((string) ($field['label'] ?? '')),
                'help' => trim((string) ($field['help'] ?? '')),
                'secret' => self::flag($field['secret'] ?? true),
                'optional' => self::flag($field['optional'] ?? false),
            ];
            $suggest = self::normalizeSuggestion($field['suggest'] ?? null);
            if ($suggest !== null) {
                $normalized['suggest'] = $suggest;
            }
            $out[] = $normalized;
        }

        return $out === [] ? null : $out;
    }

    /**
     * A suggestion as stored, or null when nothing was declared.
     *
     * The URL is what decides whether there is one: a form's empty group comes
     * back as null rather than as a suggestion with no address. Keys outside
     * {@see SUGGESTION_KEYS} are kept, so the rules can name them.
     *
     * @return array<string, mixed>|null
     */
    public static function normalizeSuggestion(mixed $suggest): ?array
    {
        if (! is_array($suggest)) {
            return null;
        }
        $url = trim((string) ($suggest['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        $body = $suggest['body'] ?? null;
        if (is_string($body)) {
            $body = trim($body) === '' ? null : json_decode($body, true);
            if ($body === null) {
                // Text that is not JSON stays text, and the rules refuse it.
                $body = (string) $suggest['body'];
            }
        }

        $out = $suggest;
        $out['method'] = strtoupper(trim((string) ($suggest['method'] ?? 'GET'))) ?: 'GET';
        $out['url'] = $url;
        $out['value_at'] = trim((string) ($suggest['value_at'] ?? ''));
        if ($body === null || $body === []) {
            unset($out['body']);
        } else {
            $out['body'] = $body;
        }

        return $out;
    }

    /**
     * Every field key a suggestion names, in its URL and in the strings of its
     * body, once each.
     *
     * @param  array<string, mixed>  $suggest
     * @return list<string>
     */
    public static function placeholdersOf(array $suggest): array
    {
        $strings = [(string) ($suggest['url'] ?? '')];
        $body = $suggest['body'] ?? null;
        if (is_array($body)) {
            array_walk_recursive($body, static function (mixed $value) use (&$strings): void {
                if (is_string($value)) {
                    $strings[] = $value;
                }
            });
        }

        $names = [];
        foreach ($strings as $string) {
            preg_match_all(self::PLACEHOLDER_REGEX, $string, $matches);
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    private static function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
