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
 * are the SAME literals the marketplace form has enforced since
 * M-MKT-AUTHZ-HARDEN-1 — this table pins them.
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
}
