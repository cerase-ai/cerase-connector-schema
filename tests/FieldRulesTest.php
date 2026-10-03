<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\CredentialFields;
use Cerase\ConnectorSchema\Rules;
use PHPUnit\Framework\TestCase;

/**
 * Every field a connector asks for says where its value is found, and a
 * suggestion is built only from the fields already typed that are not secret.
 */
final class FieldRulesTest extends TestCase
{
    /**
     * A connector reached at an address the person types, whose database name
     * the instance itself lists.
     *
     * @return array<string, mixed>
     */
    private static function descriptor(): array
    {
        return [
            'auth_kind' => 'bearer',
            'credential_parts' => [
                ['key' => 'INSTANCE_URL', 'label' => 'Instance URL', 'secret' => false,
                    'help' => 'The address you open the app at.'],
                ['key' => 'DATABASE', 'label' => 'Database', 'secret' => false,
                    'help' => 'Shown on the login page.',
                    'suggest' => [
                        'method' => 'POST',
                        'url' => '{INSTANCE_URL}/api/databases',
                        'body' => ['params' => ['owner' => '{LOGIN}']],
                        'value_at' => 'result',
                    ]],
                ['key' => 'LOGIN', 'label' => 'Login', 'secret' => false,
                    'help' => 'The email you sign in with.'],
                ['key' => 'API_KEY', 'label' => 'API key', 'secret' => true,
                    'help' => 'Profile → Security → New API key.'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @return list<string>
     */
    private static function codes(array $descriptor): array
    {
        return array_map(
            static fn (array $v): string => $v['code'].':'.$v['list'].'.'.$v['key'],
            Rules::fieldViolations($descriptor),
        );
    }

    public function test_a_well_declared_connector_has_no_violation(): void
    {
        self::assertSame([], Rules::fieldViolations(self::descriptor()));
    }

    public function test_a_field_without_its_sentence_is_refused(): void
    {
        $descriptor = self::descriptor();
        unset($descriptor['credential_parts'][3]['help']);
        $descriptor['credential_parts'][2]['help'] = '   ';

        self::assertSame([
            'field_help_required:credential_parts.LOGIN',
            'field_help_required:credential_parts.API_KEY',
        ], self::codes($descriptor));
    }

    public function test_an_oauth_app_field_needs_its_sentence_too(): void
    {
        $descriptor = [
            'auth_kind' => 'oauth2',
            'provider_fields' => [['key' => 'developer_token', 'label' => 'Developer token', 'secret' => true]],
        ];

        self::assertSame(['field_help_required:provider_fields.developer_token'], self::codes($descriptor));
    }

    public function test_a_connector_authenticating_with_a_key_declares_the_field_the_person_types(): void
    {
        self::assertSame(
            ['credential_fields_required:credential_parts.'],
            self::codes(['auth_kind' => 'bearer', 'credential_parts' => null]),
        );
        self::assertSame([], self::codes(['auth_kind' => 'oauth2']));
        self::assertSame([], self::codes(['auth_kind' => 'none']));
    }

    public function test_a_key_must_be_a_variable_name_and_unique(): void
    {
        $descriptor = self::descriptor();
        $descriptor['credential_parts'][3]['key'] = '1KEY';
        $descriptor['credential_parts'][] = $descriptor['credential_parts'][0];

        self::assertSame([
            'field_key_invalid:credential_parts.1KEY',
            'field_key_duplicate:credential_parts.INSTANCE_URL',
        ], self::codes($descriptor));
    }

    public function test_the_flags_are_booleans(): void
    {
        $descriptor = self::descriptor();
        $descriptor['credential_parts'][2]['optional'] = 'no';

        $violations = Rules::fieldViolations($descriptor);
        self::assertSame('field_flag_invalid', $violations[0]['code']);
        self::assertSame('optional', $violations[0]['detail']);
    }

    public function test_a_suggestion_may_not_name_a_secret_field(): void
    {
        $descriptor = self::descriptor();
        $descriptor['credential_parts'][1]['suggest']['url'] = '{INSTANCE_URL}/api/databases?key={API_KEY}';

        $violations = Rules::fieldViolations($descriptor);
        self::assertSame('field_suggestion_invalid', $violations[0]['code']);
        self::assertSame('DATABASE', $violations[0]['key']);
        self::assertStringContainsString('{API_KEY}', $violations[0]['detail']);
    }

    public function test_a_field_whose_secret_flag_is_left_out_counts_as_secret(): void
    {
        $descriptor = self::descriptor();
        unset($descriptor['credential_parts'][2]['secret']);

        $violations = Rules::fieldViolations($descriptor);
        self::assertSame('field_suggestion_invalid', $violations[0]['code']);
        self::assertStringContainsString('{LOGIN}', $violations[0]['detail']);
    }

    public function test_a_suggestion_may_not_name_its_own_field_or_one_that_does_not_exist(): void
    {
        $own = self::descriptor();
        $own['credential_parts'][1]['suggest']['url'] = '{DATABASE}/x';
        $missing = self::descriptor();
        $missing['credential_parts'][1]['suggest']['url'] = '{TENANT}/x';

        self::assertStringContainsString('its own field', Rules::fieldViolations($own)[0]['detail']);
        self::assertStringContainsString('{TENANT}', Rules::fieldViolations($missing)[0]['detail']);
    }

    public function test_a_suggestion_is_built_from_a_field_already_typed(): void
    {
        $descriptor = self::descriptor();
        $descriptor['credential_parts'][1]['suggest'] = [
            'url' => 'https://directory.example/databases', 'value_at' => 'result',
        ];

        self::assertStringContainsString('names no field', Rules::fieldViolations($descriptor)[0]['detail']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function malformedSuggestions(): array
    {
        return [
            'a method other than GET or POST' => [['method' => 'DELETE'], 'method'],
            'a body on a GET' => [['method' => 'GET'], 'only with POST'],
            'a body that is not JSON' => [['body' => '{not json'], 'JSON object'],
            'a path that is not a dot path' => [['value_at' => 'result[0]'], 'dot path'],
            'no path' => [['value_at' => ''], 'dot path'],
            'an address that is neither http nor a field' => [['url' => 'file:///etc/{INSTANCE_URL}'], 'http'],
            'a key nobody reads' => [['headers' => ['X' => 'y']], 'unknown key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedSuggestions')]
    public function test_a_malformed_suggestion_is_refused(array $change, string $expected): void
    {
        $descriptor = self::descriptor();
        $descriptor['credential_parts'][1]['suggest'] = array_merge($descriptor['credential_parts'][1]['suggest'], $change);

        $violations = Rules::fieldViolations($descriptor);
        self::assertCount(1, $violations);
        self::assertSame('field_suggestion_invalid', $violations[0]['code']);
        self::assertStringContainsString($expected, $violations[0]['detail']);
    }

    public function test_only_an_accounts_field_may_declare_a_suggestion(): void
    {
        $descriptor = [
            'auth_kind' => 'oauth2',
            'provider_fields' => [[
                'key' => 'developer_token', 'label' => 'Developer token', 'help' => 'API Center.',
                'suggest' => ['url' => 'https://x.example/{A}', 'value_at' => 'v'],
            ]],
        ];

        self::assertSame(['field_suggestion_not_allowed:provider_fields.developer_token'], self::codes($descriptor));
    }

    public function test_a_violation_reads_as_a_sentence_in_either_language(): void
    {
        $violation = ['code' => 'field_help_required', 'list' => 'credential_parts', 'key' => 'API_KEY', 'detail' => ''];

        self::assertSame(
            'credential_parts › API_KEY: the field has no sentence saying where its value is found (help).',
            Rules::fieldViolationMessage($violation),
        );
        self::assertStringContainsString('dove si trova il suo valore', Rules::fieldViolationMessage($violation, 'it'));
    }

    public function test_a_forms_state_is_stored_as_the_list_the_runtime_reads(): void
    {
        $state = [
            'item-a' => ['key' => ' INSTANCE_URL ', 'label' => 'Instance URL', 'help' => 'Where.', 'secret' => false, 'optional' => false,
                'suggest' => ['url' => '', 'method' => 'GET', 'value_at' => null, 'body' => null]],
            'item-b' => ['key' => 'DATABASE', 'label' => 'Database', 'help' => 'Login page.', 'secret' => false, 'optional' => true,
                'suggest' => ['url' => '{INSTANCE_URL}/api', 'method' => 'post', 'value_at' => 'result', 'body' => '{"a": "{INSTANCE_URL}"}']],
        ];

        self::assertSame([
            ['key' => 'INSTANCE_URL', 'label' => 'Instance URL', 'help' => 'Where.', 'secret' => false, 'optional' => false],
            ['key' => 'DATABASE', 'label' => 'Database', 'help' => 'Login page.', 'secret' => false, 'optional' => true,
                'suggest' => ['url' => '{INSTANCE_URL}/api', 'method' => 'POST', 'value_at' => 'result', 'body' => ['a' => '{INSTANCE_URL}']]],
        ], CredentialFields::normalize($state));
        self::assertNull(CredentialFields::normalize([]));
        self::assertNull(CredentialFields::normalize(null));
    }

    public function test_the_fields_a_suggestion_names_are_read_from_its_address_and_its_body(): void
    {
        self::assertSame(
            ['INSTANCE_URL', 'LOGIN'],
            CredentialFields::placeholdersOf(self::descriptor()['credential_parts'][1]['suggest']),
        );
    }
}
