<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\State;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The one directory a connector keeps across a recreated container: only a
 * connector that runs its own image and one runner per account declares it,
 * with an absolute path outside the platform's own directories, a maximum size
 * and whether the server stays running.
 */
final class StateTest extends TestCase
{
    private const NOT_A_MAP = 'install_state must be a mapping of path, max_mb and keep_running.';

    private const WITHOUT_IMAGE = 'install_state: only a connector that runs its own image declares the state it keeps.';

    private const NOT_PER_CONNECTION = 'install_state: the state is kept per connected account, so it is declared only by a connector that runs one runner per account.';

    private const PATH_MISSING = 'install_state: the path of the directory it keeps is missing.';

    private const MAX_MB_INVALID = 'install_state: max_mb must be a whole number from 1 to 2048.';

    private const KEEP_RUNNING_INVALID = 'install_state: keep_running must be true or false.';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function image(array $overrides = []): array
    {
        return array_replace([
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/notes:1',
            'credential_delivery' => 'env',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function state(array $overrides = []): array
    {
        return array_replace([
            'path' => '/home/node/.notes',
            'max_mb' => 512,
        ], $overrides);
    }

    private static function unknownKey(string $key): string
    {
        return "install_state: {$key} is not one of path, max_mb and keep_running.";
    }

    private static function invalidPath(string $path): string
    {
        return "install_state › {$path}: write one absolute path of at most 200 characters, made of segments of letters, digits, dot, dash and underscore, without . or .. segments, a double slash or a trailing slash, outside the platform's own directories.";
    }

    public function test_an_env_delivery_image_keeps_its_state_running(): void
    {
        self::assertSame([], State::violations(
            self::image() + ['install_state' => self::state(['keep_running' => true])],
        ));
    }

    public function test_a_file_delivery_image_may_leave_keep_running_out(): void
    {
        self::assertSame([], State::violations([
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/notes:1',
            'credential_delivery' => 'file',
            'install_state' => [
                'path' => '/home/node/.notes',
                'max_mb' => 2048,
            ],
        ]));
    }

    public function test_a_browser_login_image_keeps_the_state_of_its_own_browser(): void
    {
        self::assertSame([], State::violations([
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/notes:1',
            'auth_kind' => 'browser',
            'install_state' => [
                'path' => '/home/node/.cache/login',
                'max_mb' => 128,
            ],
        ]));
    }

    public function test_a_browser_login_keeps_its_state_even_with_header_delivery(): void
    {
        self::assertSame([], State::violations(
            self::image(['auth_kind' => 'browser', 'credential_delivery' => 'header'])
                + ['install_state' => self::state()],
        ));
    }

    public function test_an_image_mode_alone_is_an_image(): void
    {
        self::assertSame([], State::violations([
            'install_mode' => 'image',
            'credential_delivery' => 'env',
            'install_state' => self::state(),
        ]));
    }

    public function test_an_image_with_no_mode_is_an_image(): void
    {
        self::assertSame([], State::violations([
            'install_image' => 'ghcr.io/example/notes:1',
            'credential_delivery' => 'env',
            'install_state' => self::state(),
        ]));
    }

    public function test_a_null_state_declares_nothing(): void
    {
        self::assertSame([], State::violations(self::image() + ['install_state' => null]));
        self::assertSame([], State::violations(self::image()));
        self::assertSame([], State::violations([
            'install_mode' => 'remote_url',
            'install_remote_url' => 'https://mcp.example.com',
            'install_state' => null,
        ]));
    }

    public function test_a_path_with_a_dotted_segment_is_still_one_directory(): void
    {
        self::assertSame([], State::violations(
            self::image() + ['install_state' => self::state(['path' => '/home/node/.notes'])],
        ));
    }

    public function test_a_path_that_merely_starts_like_a_forbidden_directory_is_allowed(): void
    {
        self::assertSame([], State::violations(
            self::image() + ['install_state' => self::state(['path' => '/etcetera'])],
        ));
        self::assertSame([], State::violations(
            self::image() + ['install_state' => self::state(['path' => '/tmpfoo'])],
        ));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notMaps(): iterable
    {
        yield 'a string' => ['/home/node/.notes'];
        yield 'a number' => [42];
        yield 'a list' => [['/home/node/.notes', 512]];
    }

    #[DataProvider('notMaps')]
    public function test_a_state_that_is_not_a_mapping_is_refused(mixed $state): void
    {
        self::assertSame(
            [self::NOT_A_MAP],
            State::violations(self::image() + ['install_state' => $state]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownKeys(): iterable
    {
        yield 'extra' => ['extra'];
        yield 'mode' => ['mode'];
        yield 'owner' => ['owner'];
    }

    #[DataProvider('unknownKeys')]
    public function test_an_unknown_key_is_refused_and_named(string $key): void
    {
        self::assertSame(
            [self::unknownKey($key)],
            State::violations(self::image() + ['install_state' => self::state([$key => 'x'])]),
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
            'credential_delivery' => 'env',
        ]];

        yield 'a command connector' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
            'credential_delivery' => 'env',
        ]];

        yield 'a command connector that also carries an image' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
            'install_image' => 'ghcr.io/example/notes:1',
            'credential_delivery' => 'env',
        ]];

        yield 'a connector with no mode and no image' => [[
            'install_command' => 'npx -y example-mcp@1',
            'credential_delivery' => 'env',
        ]];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    #[DataProvider('notOwnImage')]
    public function test_only_a_connector_that_runs_its_own_image_declares_state(array $descriptor): void
    {
        self::assertSame(
            [self::WITHOUT_IMAGE],
            State::violations($descriptor + ['install_state' => self::state()]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function notPerConnection(): iterable
    {
        yield 'header delivery' => [['credential_delivery' => 'header']];
        yield 'no delivery' => [['credential_delivery' => null]];
        yield 'a bearer connector with no delivery' => [['auth_kind' => 'bearer', 'credential_delivery' => null]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('notPerConnection')]
    public function test_state_is_only_for_a_runner_per_connection(array $overrides): void
    {
        self::assertSame(
            [self::NOT_PER_CONNECTION],
            State::violations(self::image($overrides) + ['install_state' => self::state()]),
        );
    }

    public function test_the_path_is_required(): void
    {
        self::assertSame(
            [self::PATH_MISSING],
            State::violations(self::image() + ['install_state' => ['max_mb' => 512]]),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notStrings(): iterable
    {
        yield 'a number' => [42];
        yield 'a boolean' => [true];
    }

    #[DataProvider('notStrings')]
    public function test_a_path_that_is_not_text_is_refused(mixed $path): void
    {
        $shown = is_string($path) ? $path : (string) json_encode($path);

        self::assertSame(
            [self::invalidPath($shown)],
            State::violations(self::image() + ['install_state' => self::state(['path' => $path])]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'a relative path' => ['data/notes'];
        yield 'a dot-relative path' => ['./notes'];
        yield 'longer than 200 characters' => ['/'.str_repeat('a', 200)];
        yield 'a space' => ['/home/node/notes app'];
        yield 'a shell character' => ['/home/node/notes$'];
        yield 'a dot segment' => ['/home/./notes'];
        yield 'a dot-dot segment' => ['/home/../notes'];
        yield 'a double slash' => ['/home//notes'];
        yield 'a trailing slash' => ['/home/node/'];
        yield 'the root' => ['/'];
    }

    #[DataProvider('invalidPaths')]
    public function test_a_malformed_path_is_refused_and_named(string $path): void
    {
        self::assertSame(
            [self::invalidPath($path)],
            State::violations(self::image() + ['install_state' => self::state(['path' => $path])]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenDirectories(): iterable
    {
        yield '/proc' => ['/proc'];
        yield 'under /proc' => ['/proc/1'];
        yield '/sys' => ['/sys'];
        yield '/dev' => ['/dev'];
        yield '/etc' => ['/etc'];
        yield 'under /etc' => ['/etc/ssl'];
        yield '/usr' => ['/usr'];
        yield '/bin' => ['/bin'];
        yield '/sbin' => ['/sbin'];
        yield '/lib' => ['/lib'];
        yield '/lib32' => ['/lib32'];
        yield '/lib64' => ['/lib64'];
        yield '/libx32' => ['/libx32'];
        yield '/boot' => ['/boot'];
        yield '/run' => ['/run'];
        yield 'under /run' => ['/run/docker.sock'];
        yield '/var/run' => ['/var/run'];
        yield 'under /var/run' => ['/var/run/docker.sock'];
        yield '/tmp' => ['/tmp'];
        yield 'under /tmp' => ['/tmp/scratch'];
        yield '/opt/cerase' => ['/opt/cerase'];
        yield 'under /opt/cerase' => ['/opt/cerase/state'];
    }

    #[DataProvider('forbiddenDirectories')]
    public function test_a_platform_directory_is_refused_and_named(string $path): void
    {
        self::assertSame(
            [self::invalidPath($path)],
            State::violations(self::image() + ['install_state' => self::state(['path' => $path])]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidMaxMb(): iterable
    {
        yield 'missing' => [['path' => '/home/node/.notes']];
        yield 'a numeric string' => [['path' => '/home/node/.notes', 'max_mb' => '512']];
        yield 'zero' => [['path' => '/home/node/.notes', 'max_mb' => 0]];
        yield 'negative' => [['path' => '/home/node/.notes', 'max_mb' => -1]];
        yield 'larger than the maximum' => [['path' => '/home/node/.notes', 'max_mb' => 2049]];
        yield 'a float' => [['path' => '/home/node/.notes', 'max_mb' => 1.5]];
        yield 'a boolean' => [['path' => '/home/node/.notes', 'max_mb' => true]];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    #[DataProvider('invalidMaxMb')]
    public function test_max_mb_is_a_whole_number_from_one_to_the_maximum(array $state): void
    {
        self::assertSame(
            [self::MAX_MB_INVALID],
            State::violations(self::image() + ['install_state' => $state]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidKeepRunning(): iterable
    {
        yield 'a string' => [['path' => '/home/node/.notes', 'max_mb' => 512, 'keep_running' => 'true']];
        yield 'a number' => [['path' => '/home/node/.notes', 'max_mb' => 512, 'keep_running' => 1]];
        yield 'zero' => [['path' => '/home/node/.notes', 'max_mb' => 512, 'keep_running' => 0]];
        yield 'null' => [['path' => '/home/node/.notes', 'max_mb' => 512, 'keep_running' => null]];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    #[DataProvider('invalidKeepRunning')]
    public function test_keep_running_is_a_boolean_when_present(array $state): void
    {
        self::assertSame(
            [self::KEEP_RUNNING_INVALID],
            State::violations(self::image() + ['install_state' => $state]),
        );
    }

    public function test_every_defect_is_reported_and_none_twice(): void
    {
        $violations = State::violations(self::image() + ['install_state' => [
            'path' => '/home/node/.notes',
            'max_mb' => 0,
            'keep_running' => 'yes',
            'extra' => true,
        ]]);

        self::assertCount(3, $violations);
        self::assertContains(self::unknownKey('extra'), $violations);
        self::assertContains(self::MAX_MB_INVALID, $violations);
        self::assertContains(self::KEEP_RUNNING_INVALID, $violations);
        self::assertSame(array_values(array_unique($violations)), $violations);
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            ['install_state: solo un connettore che esegue la propria immagine dichiara lo stato che conserva.'],
            State::violations([
                'install_mode' => 'remote_url',
                'install_remote_url' => 'https://mcp.example.com',
                'credential_delivery' => 'env',
                'install_state' => self::state(),
            ], 'it'),
        );
    }

    public function test_a_forms_state_is_stored_as_the_descriptor_reads_it(): void
    {
        self::assertSame(
            ['path' => '/home/node/.notes', 'max_mb' => 512, 'keep_running' => false],
            State::normalize(['path' => ' /home/node/.notes ', 'max_mb' => '512']),
        );

        self::assertSame(
            ['path' => '/home/node/.notes', 'max_mb' => 2048, 'keep_running' => true],
            State::normalize(['path' => '/home/node/.notes', 'max_mb' => '2048', 'keep_running' => true]),
        );
    }

    public function test_a_blank_form_state_declares_nothing(): void
    {
        self::assertNull(State::normalize(null));
        self::assertNull(State::normalize([]));
        self::assertNull(State::normalize(['path' => '  ', 'max_mb' => '  ']));
        self::assertNull(State::normalize('x'));
    }

    public function test_a_form_state_with_a_size_and_no_path_is_still_declared(): void
    {
        self::assertSame(
            ['path' => '', 'max_mb' => 512, 'keep_running' => false],
            State::normalize(['path' => ' ', 'max_mb' => '512']),
        );
    }

    public function test_a_normalized_form_state_is_accepted(): void
    {
        $state = State::normalize(['path' => ' /home/node/.notes ', 'max_mb' => '64']);

        self::assertSame([], State::violations(self::image() + ['install_state' => $state]));
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame('install_state', State::KEY);
        self::assertSame(2048, State::MAX_MB);
    }
}
