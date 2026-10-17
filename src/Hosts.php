<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The hosts a connector's runner reaches outside the platform.
 *
 *     hosts:                         # the hosts outside the platform its runner reaches
 *       - api.airtable.com           # a host by name
 *       - "*.googleusercontent.com"  # any subdomain of a name
 *       - "{ODOO_URL}"               # the host of the address a connection's field holds
 *     hosts: ["*"]                   # any host, for a browser
 *     hosts: []                      # none: the runner reaches only the platform's own services
 *
 * Only a connector that runs in a runner (install mode `command` or `image`)
 * declares it; a connector reached at its own address has no runner and
 * declares none. The platform's own services are reached through the runner's
 * networks and are never listed. The list is read by name, so it names
 * hosts, never ports or paths.
 */
final class Hosts
{
    /** The descriptor key holding the declaration. */
    public const KEY = 'hosts';

    /** Any host, for a connector that follows an address a person supplies. */
    public const ANY = '*';

    /** The install modes that run the connector in a runner. */
    public const RUNNER_MODES = ['command', 'image'];

    /** A public host name, optionally for any of its subdomains. */
    public const NAME_REGEX = '~^(\*\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~i';

    /** A reference to the field whose value holds the address. */
    public const FIELD_REGEX = '~^\{([A-Za-z_][A-Za-z0-9_]*)\}$~';

    /**
     * Whether the descriptor installs the connector in a runner.
     *
     * The form's mode decides; a descriptor that states no mode falls back to
     * the install command or image it carries.
     *
     * @param  array<string, mixed>  $descriptor
     */
    public static function runsARunner(array $descriptor): bool
    {
        $mode = $descriptor['install_mode'] ?? null;
        if (is_string($mode) && $mode !== '') {
            return in_array($mode, self::RUNNER_MODES, true);
        }

        return self::hasText($descriptor['install_command'] ?? null)
            || self::hasText($descriptor['install_image'] ?? null);
    }

    /**
     * Every reason the declaration cannot be read, one sentence each, naming
     * the host. Empty when the declaration is valid.
     *
     * A connector with no runner declares nothing: null and an empty list are
     * valid, and any entry is refused. A connector with a runner must declare
     * the list, which may be empty when it reaches nothing outside the
     * platform.
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

        $hosts = $descriptor[self::KEY] ?? null;

        if (! self::runsARunner($descriptor)) {
            if ($hosts === null || $hosts === []) {
                return [];
            }

            return [$message('hosts_without_runner')];
        }

        if ($hosts === null) {
            return [$message('hosts_required')];
        }
        if (! is_array($hosts) || ($hosts !== [] && ! array_is_list($hosts))) {
            return [$message('hosts_not_a_list')];
        }

        $plain = self::notSecretFields($descriptor);
        $found = [];
        $seen = [];

        foreach ($hosts as $host) {
            $shown = self::shown($host);
            $value = is_string($host) ? trim($host) : null;

            if ($value !== null && isset($seen[strtolower($value)])) {
                $found[] = $message('host_duplicate', [':host' => $shown]);
            }
            if ($value !== null) {
                $seen[strtolower($value)] = true;
            }

            if ($value === null || $value === '') {
                $found[] = $message('host_invalid', [':host' => $shown]);

                continue;
            }

            if (preg_match(self::FIELD_REGEX, $value, $matches) === 1) {
                if (! isset($plain[$matches[1]])) {
                    $found[] = $message('host_field_unknown', [
                        ':host' => $shown,
                        ':field' => $matches[1],
                    ]);
                }

                continue;
            }

            if ($value === self::ANY) {
                if (count($hosts) > 1) {
                    $found[] = $message('host_any_not_alone');
                }

                continue;
            }

            if (preg_match(self::NAME_REGEX, $value) === 1) {
                continue;
            }

            // A name with no dot is one of the platform's own services, which
            // the runner reaches through its networks and never lists here.
            if (preg_match('~^[A-Za-z0-9_-]+$~', $value) === 1) {
                $found[] = $message('host_platform', [':host' => $shown]);

                continue;
            }

            $found[] = $message('host_invalid', [':host' => $shown]);
        }

        return array_values(array_unique($found));
    }

    /**
     * The declaration as the runtime reads it: a plain list of the entries
     * that carry a name, trimmed.
     *
     * Accepts what a form hands over as well as a YAML or JSON file. Anything
     * that is not a list, and a blank entry, is not part of it.
     *
     * @return list<string>
     */
    public static function normalize(mixed $hosts): array
    {
        if (! is_array($hosts)) {
            return [];
        }

        $out = [];
        foreach (array_values($hosts) as $host) {
            if (! is_string($host)) {
                continue;
            }
            $host = trim($host);
            if ($host !== '') {
                $out[] = $host;
            }
        }

        return $out;
    }

    /**
     * The field key a `{KEY}` entry names, or null when the entry names none.
     */
    public static function fieldOf(string $host): ?string
    {
        if (preg_match(self::FIELD_REGEX, trim($host), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The keys of both field lists that are declared not secret: only their
     * value may be used as a host, since a secret never leaves the form.
     *
     * @param  array<string, mixed>  $descriptor
     * @return array<string, true>
     */
    private static function notSecretFields(array $descriptor): array
    {
        $plain = [];
        foreach (CredentialFields::LISTS as $list) {
            $fields = CredentialFields::normalize($descriptor[$list] ?? null);
            if ($fields === null) {
                continue;
            }
            foreach ($fields as $field) {
                if (($field['secret'] ?? true) === false && $field['key'] !== '') {
                    $plain[$field['key']] = true;
                }
            }
        }

        return $plain;
    }

    /**
     * A host entry as a sentence shows it.
     */
    private static function shown(mixed $host): string
    {
        if (is_string($host)) {
            return $host;
        }

        $json = json_encode($host, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : get_debug_type($host);
    }

    private static function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
