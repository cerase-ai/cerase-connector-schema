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
 * the ones the marketplace publisher form has enforced since
 * M-MKT-AUTHZ-HARDEN-1; extracting them here makes the two apps share ONE
 * pattern instead of two copies that can drift.
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
     * form and the API can never drift (M-CONN-GUARD-1).
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

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
