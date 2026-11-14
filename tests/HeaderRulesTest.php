<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\Rules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A connector whose key travels in a header declares how it is sent: the
 * header name, the scheme that wraps the key, the field holding a basic user,
 * and any fixed extra headers built from the connector's fields. Only a
 * connector delivered by header declares a block at all, and every defect is
 * reported as one sentence. A block may not name a header the transport or the
 * platform sets.
 */
final class HeaderRulesTest extends TestCase
{
    private const ONLY_WITH_HEADER_DELIVERY = 'auth.header: only a connector whose key is sent in a header (delivery: header) declares how it is sent.';

    private const NOT_A_MAPPING = 'auth.header must be a mapping of name, scheme, basic_user and extra.';

    private const UNKNOWN_KEY = 'auth.header: prefix is not one of name, scheme, basic_user and extra.';

    private const HEADER_NAME_RULE = 'a header name is letters, digits and ! # $ % & \' * + - . ^ _ ` | ~, with no space or line break.';

    private const UNKNOWN_SCHEME = 'auth.header › scheme: token is not one of bearer, bare and basic.';

    private const BASIC_USER_REQUIRED = 'auth.header: scheme basic names the field that holds the user (basic_user).';

    private const BASIC_USER_ONLY_WITH_BASIC = 'auth.header: basic_user is read only with scheme: basic.';

    private const EXTRA_NOT_A_MAPPING = 'auth.header › extra must be a mapping of header names to values.';

    private const EXTRA_VALUE_ONE_LINE = 'auth.header › X-Client: the value is text on one line.';

    private const EXTRA_NAME_ALREADY_SENT = 'auth.header › extra › authorization: names a header already sent.';

    /**
     * One declared field, with the sentence the form shows under it.
     *
     * @return array<string, mixed>
     */
    private static function part(string $key, bool $secret = true): array
    {
        return [
            'key' => $key,
            'label' => $key,
            'help' => 'Where the person finds this value in the product.',
            'secret' => $secret,
        ];
    }

    private static function invalidName(string $name): string
    {
        return 'auth.header › '.$name.': '.self::HEADER_NAME_RULE;
    }

    private static function unknownBasicUser(string $user): string
    {
        return 'auth.header › basic_user: '.$user.' is not a field of this connector (credential_parts).';
    }

    private static function unknownExtraField(string $header, string $field): string
    {
        return 'auth.header › extra › '.$header.': '.$field.' is not a field of this connector (credential_parts).';
    }

    private static function transportHeader(string $name): string
    {
        return 'auth.header › '.$name.': the transport sets this header on every request, so a connector cannot declare it.';
    }

    private static function platformHeader(string $name): string
    {
        return 'auth.header › '.$name.': a header starting with X-Cerase- belongs to the platform.';
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedHeaders(): iterable
    {
        yield 'the default scheme' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'bearer'],
            'credential_parts' => null,
        ]];

        yield 'an empty block' => [[
            'credential_delivery' => 'header',
            'auth_header' => [],
            'credential_parts' => null,
        ]];

        yield 'a bare key in its own header' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'x-api-key', 'scheme' => 'bare'],
            'credential_parts' => null,
        ]];

        yield 'basic with the field holding the user' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'basic', 'basic_user' => 'SITE_EMAIL'],
            'credential_parts' => [
                self::part('SITE_EMAIL', false),
                self::part('API_TOKEN'),
            ],
        ]];

        yield 'fixed extra headers built from fields' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'name' => 'Authorization',
                'scheme' => 'bare',
                'extra' => [
                    'wix-account-id' => '{ACCOUNT_ID}',
                    'X-Client' => 'cerase',
                ],
            ],
            'credential_parts' => [
                self::part('ACCOUNT_ID', false),
                self::part('API_KEY'),
            ],
        ]];

        yield 'no delivery declared means header' => [[
            'credential_delivery' => null,
            'auth_header' => ['name' => 'x-api-key', 'scheme' => 'bare'],
            'credential_parts' => null,
        ]];

        yield 'names that only resemble reserved ones' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'name' => 'X-Host',
                'scheme' => 'bare',
                'extra' => [
                    'X-Ceraseful' => 'a',
                    'Accept-Language' => 'it',
                ],
            ],
            'credential_parts' => null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    #[DataProvider('acceptedHeaders')]
    public function test_a_well_declared_header_is_accepted(array $descriptor): void
    {
        self::assertSame([], Rules::headerViolations($descriptor));
    }

    public function test_a_connector_without_a_block_declares_nothing(): void
    {
        foreach ([null, 'header', 'env', 'file'] as $delivery) {
            self::assertSame([], Rules::headerViolations([
                'credential_delivery' => $delivery,
                'auth_header' => null,
                'credential_parts' => null,
            ]), 'delivery: '.var_export($delivery, true));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function refusedHeaders(): iterable
    {
        yield 'a block under env delivery' => [[
            'credential_delivery' => 'env',
            'auth_header' => ['scheme' => 'bare'],
            'credential_parts' => null,
        ], [self::ONLY_WITH_HEADER_DELIVERY]];

        yield 'a block that is a list' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['bearer'],
            'credential_parts' => null,
        ], [self::NOT_A_MAPPING]];

        yield 'a block that is a string' => [[
            'credential_delivery' => 'header',
            'auth_header' => 'bearer',
            'credential_parts' => null,
        ], [self::NOT_A_MAPPING]];

        yield 'an unknown key' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['prefix' => 'Bearer'],
            'credential_parts' => null,
        ], [self::UNKNOWN_KEY]];

        yield 'a name with a space' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'X Api Key'],
            'credential_parts' => null,
        ], [self::invalidName('X Api Key')]];

        yield 'a name with a line break and a second header' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => "X-Key\r\nX-Evil: 1"],
            'credential_parts' => null,
        ], [self::invalidName("X-Key\r\nX-Evil: 1")]];

        yield 'a name with a trailing line break' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => "X-Key\n"],
            'credential_parts' => null,
        ], [self::invalidName("X-Key\n")]];

        yield 'an empty name' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => ''],
            'credential_parts' => null,
        ], [self::invalidName('')]];

        yield 'a name that is not ASCII' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'Clé'],
            'credential_parts' => null,
        ], [self::invalidName('Clé')]];

        yield 'an unknown scheme' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'token'],
            'credential_parts' => null,
        ], [self::UNKNOWN_SCHEME]];

        yield 'basic without the field holding the user' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'basic'],
            'credential_parts' => null,
        ], [self::BASIC_USER_REQUIRED]];

        yield 'a basic user under a bare scheme' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'bare', 'basic_user' => 'SITE_EMAIL'],
            'credential_parts' => [self::part('SITE_EMAIL', false)],
        ], [self::BASIC_USER_ONLY_WITH_BASIC]];

        yield 'a basic user that is not a declared field' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['scheme' => 'basic', 'basic_user' => 'NOPE'],
            'credential_parts' => [self::part('SITE_EMAIL', false)],
        ], [self::unknownBasicUser('NOPE')]];

        yield 'an extra value naming an undeclared field' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['wix-account-id' => '{SITE}'],
            ],
            'credential_parts' => [self::part('ACCOUNT_ID', false)],
        ], [self::unknownExtraField('wix-account-id', 'SITE')]];

        yield 'extra that is a list' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['{ACCOUNT_ID}'],
            ],
            'credential_parts' => [self::part('ACCOUNT_ID', false)],
        ], [self::EXTRA_NOT_A_MAPPING]];

        yield 'an extra header name that is not valid' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['bad name' => 'x'],
            ],
            'credential_parts' => null,
        ], [self::invalidName('bad name')]];

        yield 'an extra value with a line break' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['X-Client' => "a\r\nb"],
            ],
            'credential_parts' => null,
        ], [self::EXTRA_VALUE_ONE_LINE]];

        yield 'an extra value that is not text' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['X-Client' => 5],
            ],
            'credential_parts' => null,
        ], [self::EXTRA_VALUE_ONE_LINE]];

        yield 'an extra header named as the default one' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['authorization' => 'x'],
            ],
            'credential_parts' => null,
        ], [self::EXTRA_NAME_ALREADY_SENT]];

        yield 'an unknown key and an unknown scheme together' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['prefix' => 'Bearer', 'scheme' => 'token'],
            'credential_parts' => null,
        ], [self::UNKNOWN_KEY, self::UNKNOWN_SCHEME]];

        yield 'a name set by the transport' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'Host'],
            'credential_parts' => null,
        ], [self::transportHeader('Host')]];

        yield 'a transport name in lower case' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'mcp-session-id'],
            'credential_parts' => null,
        ], [self::transportHeader('mcp-session-id')]];

        yield 'an extra header set by the transport' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['Mcp-Session-Id' => 'x'],
            ],
            'credential_parts' => null,
        ], [self::transportHeader('Mcp-Session-Id')]];

        yield 'an extra protocol header set by the transport' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['MCP-Protocol-Version' => '2025-06-18'],
            ],
            'credential_parts' => null,
        ], [self::transportHeader('MCP-Protocol-Version')]];

        yield 'an extra content type set by the transport' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['content-type' => 'text/plain'],
            ],
            'credential_parts' => null,
        ], [self::transportHeader('content-type')]];

        yield 'a name owned by the platform' => [[
            'credential_delivery' => 'header',
            'auth_header' => ['name' => 'X-Cerase-Runner-Token'],
            'credential_parts' => null,
        ], [self::platformHeader('X-Cerase-Runner-Token')]];

        yield 'an extra header owned by the platform' => [[
            'credential_delivery' => 'header',
            'auth_header' => [
                'scheme' => 'bare',
                'extra' => ['x-cerase-agent' => 'a'],
            ],
            'credential_parts' => null,
        ], [self::platformHeader('x-cerase-agent')]];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $expected
     */
    #[DataProvider('refusedHeaders')]
    public function test_a_malformed_header_is_refused(array $descriptor, array $expected): void
    {
        self::assertSame($expected, Rules::headerViolations($descriptor));
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            ['auth.header: solo un connettore la cui chiave viaggia in un header (delivery: header) dichiara come la invia.'],
            Rules::headerViolations([
                'credential_delivery' => 'env',
                'auth_header' => ['scheme' => 'bare'],
                'credential_parts' => null,
            ], 'it'),
        );
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame(['bearer', 'bare', 'basic'], Rules::HEADER_SCHEMES);
        self::assertSame('Authorization', Rules::HEADER_DEFAULT_NAME);
        self::assertContains('Host', Rules::HEADERS_SET_BY_TRANSPORT);
        self::assertContains('Mcp-Session-Id', Rules::HEADERS_SET_BY_TRANSPORT);
        self::assertSame('X-Cerase-', Rules::PLATFORM_HEADER_PREFIX);
    }

    public function test_a_transport_header_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            ['auth.header › Host: questo header lo imposta il trasporto a ogni richiesta, quindi un connettore non può dichiararlo.'],
            Rules::headerViolations([
                'credential_delivery' => 'header',
                'auth_header' => ['name' => 'Host'],
                'credential_parts' => null,
            ], 'it'),
        );
    }

    public function test_a_reserved_header_is_named_by_the_list_or_the_platform_prefix(): void
    {
        foreach (['host', 'MCP-SESSION-ID', 'Last-Event-ID', 'x-cerase-runner-token'] as $name) {
            self::assertTrue(Rules::isReservedHeader($name), $name);
        }

        foreach (['x-api-key', 'wix-account-id', 'Authorization', 'X-Host'] as $name) {
            self::assertFalse(Rules::isReservedHeader($name), $name);
        }
    }
}
