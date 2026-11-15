<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\Login;
use Cerase\ConnectorSchema\Rules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How a sign-in made in the connector's own browser runs: only a `browser`
 * connector declares it, in a block of arguments for its own image and an
 * optional viewer port, and the login is kept in the connector's state.
 */
final class LoginTest extends TestCase
{
    private const NON_BROWSER = 'auth_login: only a connector whose sign-in is made in its own browser declares how it runs.';

    private const REQUIRED = 'auth_login: a connector whose sign-in is made in its own browser declares how it runs.';

    private const NOT_A_MAP = 'auth_login must be a mapping of args and port.';

    private const ARGS_INVALID = 'auth_login: args must be a list of 1 to 8 arguments.';

    private const PORT_INVALID = 'auth_login: port must be a whole number from 1024 to 65535, other than 3000 and 3001.';

    private const WITHOUT_IMAGE = 'auth_login: a sign-in in the connector\'s own browser runs in its own image.';

    private const WITHOUT_STATE = 'auth_login: the sign-in is kept in the connector\'s state, so a connector whose sign-in is made in its own browser declares one.';

    private const IT_WITHOUT_IMAGE = 'auth_login: un accesso nel browser del connettore si svolge nella sua propria immagine.';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function descriptor(array $overrides = []): array
    {
        return array_replace([
            'auth_kind' => 'browser',
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/browser:1',
            'install_state' => ['path' => '/home/node/.login', 'max_mb' => 128],
            'auth_login' => self::login(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function login(array $overrides = []): array
    {
        return array_replace(['args' => ['--login']], $overrides);
    }

    private static function unknownKey(string $key): string
    {
        return "auth_login: {$key} is not one of args and port.";
    }

    private static function badArg(mixed $arg): string
    {
        $shown = is_string($arg) ? $arg : (string) json_encode($arg);

        return "auth_login › args › {$shown}: an argument is 1 to 100 characters of letters, digits and _ . / : = @ % + -.";
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame('auth_login', Login::KEY);
    }

    public function test_a_browser_login_with_args_only_is_accepted(): void
    {
        self::assertSame([], Login::violations(self::descriptor()));
    }

    public function test_a_browser_login_with_args_and_a_port_is_accepted(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login(['port' => 6080]),
        ])));
    }

    public function test_a_browser_login_accepts_the_port_bounds(): void
    {
        foreach ([1024, 65535] as $port) {
            self::assertSame([], Login::violations(self::descriptor([
                'auth_login' => self::login(['port' => $port]),
            ])), "port {$port}");
        }
    }

    public function test_an_argument_may_use_the_image_argument_alphabet(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login([
                'args' => ['a_b./c:d=e@f%g+h-i', str_repeat('a', 100)],
            ]),
        ])));
    }

    public function test_a_browser_descriptor_passes_the_served_kind_rule(): void
    {
        self::assertSame([], Rules::crossFieldViolations(['auth_kind' => 'browser']));
    }

    public function test_a_non_browser_login_is_refused(): void
    {
        self::assertSame(
            [self::NON_BROWSER],
            Login::violations(self::descriptor([
                'auth_kind' => 'none',
                'auth_login' => self::login(['port' => 6080]),
            ])),
        );
    }

    public function test_a_non_browser_descriptor_may_leave_the_login_absent(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_kind' => 'oauth2',
            'auth_login' => null,
        ])));

        self::assertSame([], Login::violations([
            'auth_kind' => 'bearer',
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/browser:1',
            'install_state' => ['path' => '/home/node/.login', 'max_mb' => 128],
        ]));
    }

    public function test_a_browser_login_is_required(): void
    {
        self::assertSame(
            [self::REQUIRED],
            Login::violations(self::descriptor(['auth_login' => null])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notMaps(): iterable
    {
        yield 'a string' => ['--login'];
        yield 'a number' => [42];
        yield 'a list' => [['--login', 6080]];
    }

    #[DataProvider('notMaps')]
    public function test_a_login_that_is_not_a_mapping_is_refused(mixed $login): void
    {
        self::assertSame(
            [self::NOT_A_MAP],
            Login::violations(self::descriptor(['auth_login' => $login])),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownKeys(): iterable
    {
        yield 'extra' => ['extra'];
        yield 'mode' => ['mode'];
        yield 'viewer' => ['viewer'];
    }

    #[DataProvider('unknownKeys')]
    public function test_an_unknown_key_is_refused_and_named(string $key): void
    {
        self::assertSame(
            [self::unknownKey($key)],
            Login::violations(self::descriptor([
                'auth_login' => self::login([$key => 'x']),
            ])),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidArgs(): iterable
    {
        yield 'missing' => [['port' => 6080]];
        yield 'empty' => [['args' => []]];
        yield 'a string' => [['args' => '--login']];
        yield 'a map' => [['args' => ['login' => '--login']]];
        yield 'too many' => [['args' => array_fill(0, 9, '--login')]];
    }

    /**
     * @param  array<string, mixed>  $login
     */
    #[DataProvider('invalidArgs')]
    public function test_args_must_be_a_list_of_one_to_eight(array $login): void
    {
        self::assertSame(
            [self::ARGS_INVALID],
            Login::violations(self::descriptor(['auth_login' => $login])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badArgs(): iterable
    {
        yield 'a space' => ['--log in'];
        yield 'a shell character' => ['--login;rm'];
        yield 'a quote' => ['--login"'];
        yield 'longer than 100 characters' => [str_repeat('a', 101)];
        yield 'a number' => [42];
    }

    #[DataProvider('badArgs')]
    public function test_a_bad_argument_is_refused_and_named(mixed $arg): void
    {
        self::assertSame(
            [self::badArg($arg)],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['args' => ['--login', $arg]]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPorts(): iterable
    {
        yield 'a numeric string' => ['6080'];
        yield 'a float' => [6080.5];
        yield 'a boolean' => [true];
        yield 'below the range' => [1023];
        yield 'above the range' => [65536];
        yield 'the first platform port' => [3000];
        yield 'the second platform port' => [3001];
    }

    #[DataProvider('invalidPorts')]
    public function test_a_port_is_a_whole_number_from_1024_to_65535_other_than_the_platform_ports(mixed $port): void
    {
        self::assertSame(
            [self::PORT_INVALID],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['port' => $port]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function notOwnImage(): iterable
    {
        yield 'a remote connector' => [[
            'install_mode' => 'remote_url',
            'install_remote_url' => 'https://mcp.example.com',
        ]];

        yield 'a command connector' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
        ]];

        yield 'a command connector that also carries an image' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
            'install_image' => 'ghcr.io/example/browser:1',
        ]];

        yield 'a connector with no mode and no image' => [[
            'install_mode' => null,
            'install_image' => null,
            'install_command' => 'npx -y example-mcp@1',
        ]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('notOwnImage')]
    public function test_a_browser_login_runs_its_own_image(array $overrides): void
    {
        self::assertSame(
            [self::WITHOUT_IMAGE],
            Login::violations(self::descriptor($overrides)),
        );
    }

    public function test_a_browser_login_with_no_mode_but_an_image_runs_its_own_image(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'install_mode' => null,
            'install_image' => 'ghcr.io/example/browser:1',
        ])));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function withoutState(): iterable
    {
        yield 'null' => [['install_state' => null]];
        yield 'empty' => [['install_state' => []]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('withoutState')]
    public function test_a_browser_login_declares_a_state(array $overrides): void
    {
        self::assertSame(
            [self::WITHOUT_STATE],
            Login::violations(self::descriptor($overrides)),
        );
    }

    public function test_a_browser_login_without_a_state_key_is_refused(): void
    {
        $descriptor = self::descriptor();
        unset($descriptor['install_state']);

        self::assertSame([self::WITHOUT_STATE], Login::violations($descriptor));
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            [self::IT_WITHOUT_IMAGE],
            Login::violations(self::descriptor([
                'install_mode' => 'remote_url',
                'install_remote_url' => 'https://mcp.example.com',
            ]), 'it'),
        );
    }

    public function test_a_forms_login_is_stored_as_the_descriptor_reads_it(): void
    {
        self::assertSame(
            ['args' => ['--login'], 'port' => null],
            Login::normalize(['args' => [' --login ', '', '   ']]),
        );

        self::assertSame(
            ['args' => ['--login', '--login-viewer'], 'port' => 6080],
            Login::normalize([
                'args' => [' --login ', '', '--login-viewer', '  '],
                'port' => '6080',
            ]),
        );

        self::assertSame(
            ['args' => ['--login'], 'port' => 6080],
            Login::normalize(['args' => ['--login'], 'port' => 6080]),
        );
    }

    public function test_a_blank_form_login_declares_nothing(): void
    {
        self::assertNull(Login::normalize(null));
        self::assertNull(Login::normalize([]));
        self::assertNull(Login::normalize(['args' => [], 'port' => '']));
        self::assertNull(Login::normalize(['args' => ['  '], 'port' => '  ']));
        self::assertNull(Login::normalize('--login'));
    }

    public function test_a_normalized_form_login_is_accepted(): void
    {
        $login = Login::normalize([
            'args' => [' --login ', '', '--login-viewer'],
            'port' => '6080',
        ]);

        self::assertSame([], Login::violations(self::descriptor(['auth_login' => $login])));

        // A form that leaves the port empty stores it as null, which declares no viewer.
        $qr = Login::normalize(['args' => ['--login'], 'port' => '']);

        self::assertSame([], Login::violations(self::descriptor(['auth_login' => $qr])));
    }
}
