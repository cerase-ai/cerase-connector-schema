<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\Hosts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The hosts a connector declares: only a connector that runs in a runner
 * declares them, each entry is a public host name, a `*.` wildcard, a
 * `{FIELD}` of a field declared not secret, or `*` alone, and every defect is
 * reported once in either language.
 */
final class HostsTest extends TestCase
{
    private const HOSTS_REQUIRED = 'hosts: a connector that runs in a runner declares the hosts it reaches outside the platform, or an empty list when it reaches none.';

    private const HOSTS_WITHOUT_RUNNER = 'hosts: a connector reached at its own address runs no runner, so it declares no hosts.';

    private const HOSTS_NOT_A_LIST = 'hosts must be a list of host names.';

    private const HOST_ANY_NOT_ALONE = 'hosts: * already means any host, so it is the only entry.';

    /**
     * A connector this library's own tests install with a command.
     *
     * @return array<string, mixed>
     */
    private static function command(): array
    {
        return [
            'install_mode' => 'command',
            'install_command' => 'npx -y airtable-mcp-server@1.13.0',
        ];
    }

    private static function invalidHost(string $host): string
    {
        return "hosts › {$host}: write a host name such as api.example.com, *.example.com for any of its subdomains, {FIELD} for the host of the address a connection's field holds, or * for any host, with no scheme, port or path.";
    }

    private static function platformHost(string $host): string
    {
        return "hosts › {$host}: is not a public host name. A runner reaches the platform's own services through its networks, never through this list.";
    }

    private static function duplicateHost(string $host): string
    {
        return "hosts › {$host}: named twice.";
    }

    private static function unknownField(string $host, string $field): string
    {
        return "hosts › {$host}: {$field} is not a field of this connector declared secret: false.";
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedDeclarations(): iterable
    {
        yield 'a command reaching two API hosts' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y airtable-mcp-server@1.13.0',
            'hosts' => ['api.airtable.com', 'content.airtable.com'],
        ]];

        yield 'an image reaching no host' => [[
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/cerase/connector:1',
            'hosts' => [],
        ]];

        yield 'an image reaching any host' => [[
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/cerase/connector:1',
            'hosts' => ['*'],
        ]];

        yield 'a command reaching a host and its subdomains' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y google-workspace-mcp@1',
            'hosts' => ['www.googleapis.com', '*.googleusercontent.com'],
        ]];

        yield 'a command reaching the host of a non-secret field' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y odoo-mcp@1',
            'hosts' => ['{ODOO_URL}'],
            'credential_parts' => [
                ['key' => 'ODOO_URL', 'label' => 'Odoo URL', 'help' => 'The address you open Odoo at.', 'secret' => false],
                ['key' => 'ODOO_PASSWORD', 'label' => 'Password', 'help' => 'The password you sign in with.'],
            ],
        ]];

        yield 'a host name in mixed case' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y airtable-mcp-server@1.13.0',
            'hosts' => ['API.Airtable.com'],
        ]];

        yield 'a provider field declared not secret' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y zendesk-mcp@1',
            'hosts' => ['{INSTANCE_URL}'],
            'provider_fields' => [
                ['key' => 'INSTANCE_URL', 'label' => 'Instance URL', 'help' => 'The address you open Zendesk at.', 'secret' => false],
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    #[DataProvider('acceptedDeclarations')]
    public function test_a_declaration_a_catalogue_carries_is_accepted(array $descriptor): void
    {
        self::assertSame([], Hosts::violations($descriptor));
    }

    public function test_a_connector_reached_at_its_url_declares_no_hosts(): void
    {
        $remote = [
            'install_mode' => 'remote_url',
            'install_remote_url' => 'https://mcp.notion.com',
        ];

        self::assertSame([], Hosts::violations($remote));
        self::assertSame([], Hosts::violations($remote + ['hosts' => null]));
        self::assertSame([], Hosts::violations($remote + ['hosts' => []]));
        self::assertSame(
            [self::HOSTS_WITHOUT_RUNNER],
            Hosts::violations($remote + ['hosts' => ['mcp.notion.com']]),
        );
    }

    public function test_a_skill_package_declares_no_hosts(): void
    {
        self::assertSame([], Hosts::violations([]));
    }

    public function test_a_runner_without_a_declaration_is_refused(): void
    {
        self::assertSame([self::HOSTS_REQUIRED], Hosts::violations(self::command()));

        self::assertSame([self::HOSTS_REQUIRED], Hosts::violations([
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/cerase/connector:1',
            'hosts' => null,
        ]));
    }

    public function test_the_forms_mode_decides_whether_a_runner_is_used(): void
    {
        self::assertSame([self::HOSTS_REQUIRED], Hosts::violations(['install_mode' => 'command']));
        self::assertSame([], Hosts::violations([
            'install_mode' => 'remote_url',
            'install_command' => 'npx -y stale-command@1',
        ]));

        self::assertTrue(Hosts::runsARunner(['install_mode' => 'image']));
        self::assertFalse(Hosts::runsARunner(['install_mode' => 'remote_url']));
        self::assertFalse(Hosts::runsARunner(['install_mode' => 'none']));
        self::assertTrue(Hosts::runsARunner(['install_image' => 'ghcr.io/cerase/connector:1']));
        self::assertFalse(Hosts::runsARunner([]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notLists(): iterable
    {
        yield 'a string' => ['api.airtable.com'];
        yield 'a map with string keys' => [['a' => 'api.airtable.com']];
        yield 'a number' => [42];
    }

    #[DataProvider('notLists')]
    public function test_hosts_that_are_not_a_list_are_refused(mixed $hosts): void
    {
        self::assertSame(
            [self::HOSTS_NOT_A_LIST],
            Hosts::violations(self::command() + ['hosts' => $hosts]),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedHosts(): iterable
    {
        yield 'a scheme' => ['https://api.airtable.com'];
        yield 'a path' => ['api.airtable.com/v0'];
        yield 'a port' => ['api.airtable.com:443'];
        yield 'a wildcard without a name' => ['*.com'];
        yield 'a double dot' => ['api..airtable.com'];
        yield 'a leading hyphen' => ['-api.airtable.com'];
        yield 'a space' => ['api airtable.com'];
        yield 'a blank entry' => [''];
        yield 'a number' => [42];
    }

    #[DataProvider('malformedHosts')]
    public function test_a_malformed_host_is_refused(mixed $host): void
    {
        $shown = is_string($host) ? $host : (string) json_encode($host);

        self::assertSame(
            [self::invalidHost($shown)],
            Hosts::violations(self::command() + ['hosts' => [$host]]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function platformNames(): iterable
    {
        yield 'localhost' => ['localhost'];
        yield 'a service name' => ['postgres'];
        yield 'the control plane' => ['cerase-control-plane'];
    }

    #[DataProvider('platformNames')]
    public function test_a_platform_name_is_refused(string $host): void
    {
        self::assertSame(
            [self::platformHost($host)],
            Hosts::violations(self::command() + ['hosts' => [$host]]),
        );
    }

    public function test_a_wildcard_is_the_only_entry(): void
    {
        self::assertContains(
            self::HOST_ANY_NOT_ALONE,
            Hosts::violations(self::command() + ['hosts' => ['*', 'api.airtable.com']]),
        );
    }

    public function test_a_host_named_twice_is_refused(): void
    {
        self::assertContains(
            self::duplicateHost('API.airtable.com'),
            Hosts::violations(self::command() + ['hosts' => ['api.airtable.com', 'API.airtable.com']]),
        );
    }

    public function test_a_field_reference_needs_a_field_declared_not_secret(): void
    {
        self::assertSame(
            [self::unknownField('{ODOO_URL}', 'ODOO_URL')],
            Hosts::violations(self::command() + ['hosts' => ['{ODOO_URL}']]),
        );

        self::assertSame(
            [self::unknownField('{ODOO_PASSWORD}', 'ODOO_PASSWORD')],
            Hosts::violations(self::command() + [
                'hosts' => ['{ODOO_PASSWORD}'],
                'credential_parts' => [
                    ['key' => 'ODOO_PASSWORD', 'label' => 'Password', 'help' => 'The password you sign in with.'],
                ],
            ]),
        );

        self::assertSame(
            [self::invalidHost('{lower-case}')],
            Hosts::violations(self::command() + ['hosts' => ['{lower-case}']]),
        );
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            ['hosts: un connettore che gira in un runner dichiara gli host che raggiunge fuori dalla piattaforma, oppure una lista vuota se non ne raggiunge nessuno.'],
            Hosts::violations(['install_mode' => 'command'], 'it'),
        );

        self::assertSame(
            ["hosts › https://x: scrivi un nome di host come api.esempio.it, *.esempio.it per tutti i suoi sottodomini, {CAMPO} per l'host dell'indirizzo scritto in un campo del collegamento, oppure * per qualunque host, senza schema, porta o percorso."],
            Hosts::violations(self::command() + ['hosts' => ['https://x']], 'it'),
        );

        self::assertSame(
            ["hosts › {ODOO_URL}: ODOO_URL non è un campo di questo connettore dichiarato secret: false."],
            Hosts::violations(self::command() + ['hosts' => ['{ODOO_URL}']], 'it'),
        );
    }

    public function test_every_defect_is_reported_and_none_twice(): void
    {
        $violations = Hosts::violations(self::command() + [
            'hosts' => ['*', 'https://x', 'postgres', 'postgres'],
        ]);

        self::assertCount(4, $violations);
        self::assertContains(self::HOST_ANY_NOT_ALONE, $violations);
        self::assertContains(self::invalidHost('https://x'), $violations);
        self::assertContains(self::platformHost('postgres'), $violations);
        self::assertContains(self::duplicateHost('postgres'), $violations);
        self::assertSame(array_values(array_unique($violations)), $violations);
    }

    public function test_a_forms_state_is_stored_as_the_list_the_runtime_reads(): void
    {
        self::assertSame(
            ['api.airtable.com', 'content.airtable.com'],
            Hosts::normalize([' api.airtable.com ', '', '  ', 'content.airtable.com']),
        );
        self::assertSame([], Hosts::normalize(null));
        self::assertSame([], Hosts::normalize('x'));
        self::assertSame(['a.b.com'], Hosts::normalize([3 => 'a.b.com']));
    }

    public function test_a_field_reference_names_its_key(): void
    {
        self::assertSame('ODOO_URL', Hosts::fieldOf('{ODOO_URL}'));
        self::assertNull(Hosts::fieldOf('api.airtable.com'));
        self::assertNull(Hosts::fieldOf('*'));
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame('hosts', Hosts::KEY);
        self::assertSame('*', Hosts::ANY);
        self::assertSame(['command', 'image'], Hosts::RUNNER_MODES);
    }
}
