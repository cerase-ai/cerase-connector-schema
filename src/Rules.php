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
}
