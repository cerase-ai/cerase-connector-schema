<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\Rules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The two format rules are the security boundary of the descriptor: the
 * install command string is served to the sandboxed runner (block shell
 * metacharacters) and the image ref must be a bare OCI reference. Both regexes
 * are the SAME literals the marketplace form enforces — this table pins them.
 */
final class RulesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validCommands(): array
    {
        return [
            'plain npx' => ['npx -y airtable-mcp-server'],
            'scoped npm package' => ['npx -y @acme/mcp-server'],
            'pinned version' => ['npx -y airtable-mcp-server@1.2.3'],
            'path-like binary' => ['/usr/local/bin/mcp-server'],
            'percent + colon allowed' => ['run --flag 50% host:1234'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCommands(): array
    {
        return [
            'semicolon chaining' => ['npx foo; rm -rf /'],
            'pipe' => ['cat x | sh'],
            'command substitution' => ['echo $(whoami)'],
            'backticks' => ['echo `id`'],
            'ampersand background' => ['sleep 1 & rm x'],
            'redirection' => ['cmd > /etc/passwd'],
            'newline injection' => ["npx foo\nrm -rf /"],
            'double quote' => ['npx "foo"'],
            'single quote' => ["npx 'foo'"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validImages(): array
    {
        return [
            'ghcr with tag' => ['ghcr.io/cerase-ai/cerase-memory-mcp:latest'],
            'docker hub short' => ['nginx:1.27-alpine'],
            'digest ref' => ['ghcr.io/x/y@sha256:abcdef0123456789'],
            'port in registry' => ['registry.example.com:5000/team/app:v2'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidImages(): array
    {
        return [
            'command substitution' => ['ghcr.io/x/y:latest$(whoami)'],
            'space (not allowed in image ref)' => ['ghcr.io/x/y latest'],
            'semicolon' => ['nginx:latest;id'],
            'pipe' => ['nginx|sh'],
            'ampersand' => ['nginx&x'],
            'backtick' => ['nginx`id`'],
        ];
    }

    #[DataProvider('validCommands')]
    public function test_valid_command_accepted(string $command): void
    {
        self::assertTrue(Rules::commandIsValid($command), "expected VALID: {$command}");
    }

    #[DataProvider('invalidCommands')]
    public function test_invalid_command_rejected(string $command): void
    {
        self::assertFalse(Rules::commandIsValid($command), "expected REJECTED: {$command}");
    }

    #[DataProvider('validImages')]
    public function test_valid_image_accepted(string $image): void
    {
        self::assertTrue(Rules::imageIsValid($image), "expected VALID: {$image}");
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_image_rejected(string $image): void
    {
        self::assertFalse(Rules::imageIsValid($image), "expected REJECTED: {$image}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pinnedImages(): array
    {
        $digest = str_repeat('a', 64);

        return [
            'digest only' => ["ghcr.io/acme/x@sha256:{$digest}"],
            'tag and digest' => ["ghcr.io/acme/x:1.2@sha256:{$digest}"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unpinnedImages(): array
    {
        $digest = str_repeat('a', 64);

        return [
            'tag only' => ['ghcr.io/acme/x:latest'],
            'no tag or digest' => ['ghcr.io/acme/x'],
            'digest one character short' => ['ghcr.io/acme/x@sha256:'.str_repeat('a', 63)],
            'sha512 digest' => ["ghcr.io/acme/x@sha512:{$digest}"],
            'uppercase hex' => ['ghcr.io/acme/x@sha256:'.str_repeat('A', 64)],
            'fails the image regex' => ["ghcr.io/acme/x@sha256:{$digest}!"],
        ];
    }

    #[DataProvider('pinnedImages')]
    public function test_an_image_pinned_by_digest_is_accepted(string $image): void
    {
        self::assertTrue(Rules::isPinnedImage($image), "expected PINNED: {$image}");
    }

    #[DataProvider('unpinnedImages')]
    public function test_an_image_not_pinned_by_digest_is_refused(string $image): void
    {
        self::assertFalse(Rules::isPinnedImage($image), "expected UNPINNED: {$image}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicHttpsUrls(): array
    {
        return [
            'a host and path' => ['https://mcp.example.com/mcp'],
            'a host and port' => ['https://mcp.example.com:8443/x'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPublicHttpsUrls(): array
    {
        return [
            'plain http' => ['http://mcp.example.com/mcp'],
            'localhost' => ['https://localhost/x'],
            'a localhost subdomain' => ['https://api.localhost/x'],
            'a single label' => ['https://intranet/x'],
            'a private IPv4 address' => ['https://192.168.1.1/x'],
            'a link-local IPv4 address' => ['https://169.254.169.254/x'],
            'the IPv6 loopback' => ['https://[::1]/x'],
            'a private IPv6 address' => ['https://[fd00::1]/x'],
            'a private IPv4 address with a port' => ['https://10.0.0.1:8443/x'],
            'an empty string' => [''],
            'no scheme' => ['mcp.example.com/mcp'],
        ];
    }

    #[DataProvider('publicHttpsUrls')]
    public function test_a_public_https_url_is_accepted(string $url): void
    {
        self::assertTrue(Rules::isPublicHttpsUrl($url), "expected PUBLIC: {$url}");
    }

    #[DataProvider('nonPublicHttpsUrls')]
    public function test_a_url_that_is_not_a_public_https_url_is_refused(string $url): void
    {
        self::assertFalse(Rules::isPublicHttpsUrl($url), "expected NOT PUBLIC: {$url}");
    }

    public function test_regex_literals_match_the_marketplace_source(): void
    {
        // These byte-exact literals are what PackageResource has shipped; the
        // whole point of the package is that both apps use the SAME pattern.
        self::assertSame('~^[A-Za-z0-9 \-_./:@%+]+$~', Rules::COMMAND_REGEX);
        self::assertSame('~^[A-Za-z0-9._/:@\-+]+$~', Rules::IMAGE_REGEX);
    }

    public function test_laravel_rule_strings_prefix_the_pattern(): void
    {
        self::assertSame('regex:'.Rules::COMMAND_REGEX, Rules::commandRule());
        self::assertSame('regex:'.Rules::IMAGE_REGEX, Rules::imageRule());
    }

    // ── strict cross-field rules ──────────────────────────────────────────────
    //
    // The two strict rules the forms enforce via ->required() under
    // strictCrossField() are also exposed as a PURE function so the server side
    // (the control-plane's CustomConnectorRegistrar) enforces the IDENTICAL set
    // — one definition, no drift between the form and the API/maintainer path.

    public function test_a_fully_specified_descriptor_has_no_cross_field_violations(): void
    {
        self::assertSame([], Rules::crossFieldViolations([
            'auth_kind' => 'oauth2',
            'auth_provider' => 'notion',
            'credential_delivery' => 'env',
            'credential_env' => 'NOTION_TOKEN',
        ]));
    }

    public function test_env_delivery_without_credential_env_is_flagged(): void
    {
        self::assertSame(
            [Rules::VIOLATION_CREDENTIAL_ENV_REQUIRED],
            Rules::crossFieldViolations([
                'auth_kind' => 'bearer',
                'credential_delivery' => 'env',
                // credential_env missing
            ]),
        );

        // Blank string counts as missing.
        self::assertSame(
            [Rules::VIOLATION_CREDENTIAL_ENV_REQUIRED],
            Rules::crossFieldViolations(['credential_delivery' => 'env', 'credential_env' => '  ']),
        );
    }

    public function test_oauth2_without_provider_is_flagged(): void
    {
        self::assertSame(
            [Rules::VIOLATION_AUTH_PROVIDER_REQUIRED],
            Rules::crossFieldViolations([
                'auth_kind' => 'oauth2',
                // auth_provider missing
            ]),
        );

        self::assertSame(
            [Rules::VIOLATION_AUTH_PROVIDER_REQUIRED],
            Rules::crossFieldViolations(['auth_kind' => 'oauth2', 'auth_provider' => '']),
        );
    }

    public function test_both_rules_can_fire_together_in_a_stable_order(): void
    {
        self::assertSame(
            [Rules::VIOLATION_CREDENTIAL_ENV_REQUIRED, Rules::VIOLATION_AUTH_PROVIDER_REQUIRED],
            Rules::crossFieldViolations([
                'auth_kind' => 'oauth2',
                'credential_delivery' => 'env',
            ]),
        );
    }

    public function test_non_triggering_deliveries_and_kinds_are_never_flagged(): void
    {
        // header/file delivery never needs credential_env; none/bearer never
        // needs a provider — the rules are strictly conditional.
        self::assertSame([], Rules::crossFieldViolations(['credential_delivery' => 'header']));
        self::assertSame([], Rules::crossFieldViolations(['credential_delivery' => 'file']));
        self::assertSame([], Rules::crossFieldViolations(['auth_kind' => 'none']));
        self::assertSame([], Rules::crossFieldViolations(['auth_kind' => 'bearer']));
        self::assertSame([], Rules::crossFieldViolations([]));
    }
}
