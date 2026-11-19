<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Cerase\ConnectorSchema\Labels;
use Cerase\ConnectorSchema\Memory;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The memory a connector's container gets: an optional whole number of
 * megabytes between the platform's floor and ceiling, declared only by a
 * connector that runs a container, and read from the form as the number the
 * descriptor stores.
 */
final class MemoryTest extends TestCase
{
    private const INVALID = 'install_memory_mb: must be a whole number from 256 to 4096 (megabytes).';

    private const WITHOUT_CONTAINER = 'install_memory_mb: a connector reached at its own address runs no container, so it declares no memory.';

    private const INVALID_IT = 'install_memory_mb: dev\'essere un numero intero da 256 a 4096 (megabyte).';

    private const WITHOUT_CONTAINER_IT = 'install_memory_mb: un connettore raggiunto al proprio indirizzo non esegue alcun container, quindi non dichiara memoria.';

    private ?Container $previousContainer = null;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();

        $container = new Container();
        $container->instance('translator', new class implements Translator {
            public function get($key, array $replace = [], $locale = null): string
            {
                return (string) $key;
            }

            public function choice($key, $number, array $replace = [], $locale = null): string
            {
                return (string) $key;
            }

            public function getLocale(): string
            {
                return 'en';
            }

            public function setLocale($locale): void {}
        });

        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        $this->previousContainer = null;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function image(array $overrides = []): array
    {
        return array_replace([
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/notes:1',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function command(array $overrides = []): array
    {
        return array_replace([
            'install_mode' => 'command',
            'install_command' => 'npx -y example-notes-mcp@1.0.0',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function remote(array $overrides = []): array
    {
        return array_replace([
            'install_mode' => 'remote_url',
            'install_remote_url' => 'https://mcp.example.com',
        ], $overrides);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedDeclarations(): iterable
    {
        yield 'the lower bound on an image' => [self::image() + ['install_memory_mb' => 256]];
        yield 'the upper bound on an image' => [self::image() + ['install_memory_mb' => 4096]];
        yield 'a typical value on an image' => [self::image() + ['install_memory_mb' => 512]];
        yield 'the lower bound on a command' => [self::command() + ['install_memory_mb' => 256]];
        yield 'the upper bound on a command' => [self::command() + ['install_memory_mb' => 4096]];
        yield 'a typical value on a command' => [self::command() + ['install_memory_mb' => 512]];
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    #[DataProvider('acceptedDeclarations')]
    public function test_a_memory_in_range_is_accepted(array $descriptor): void
    {
        self::assertSame([], Memory::violations($descriptor));
    }

    public function test_an_absent_or_null_memory_is_valid_on_any_install_kind(): void
    {
        self::assertSame([], Memory::violations(self::image()));
        self::assertSame([], Memory::violations(self::image() + ['install_memory_mb' => null]));
        self::assertSame([], Memory::violations(self::command()));
        self::assertSame([], Memory::violations(self::command() + ['install_memory_mb' => null]));
        self::assertSame([], Memory::violations(self::remote()));
        self::assertSame([], Memory::violations(self::remote() + ['install_memory_mb' => null]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'below the minimum' => [255];
        yield 'above the maximum' => [4097];
        yield 'a float' => [300.5];
        yield 'a numeric string' => ['512'];
        yield 'negative' => [-1];
        yield 'zero' => [0];
        yield 'a boolean' => [true];
    }

    #[DataProvider('invalidValues')]
    public function test_a_memory_outside_the_range_is_refused(mixed $value): void
    {
        self::assertSame(
            [self::INVALID],
            Memory::violations(self::image() + ['install_memory_mb' => $value]),
        );

        self::assertSame(
            [self::INVALID],
            Memory::violations(self::command() + ['install_memory_mb' => $value]),
        );
    }

    public function test_a_remote_connector_declares_no_memory(): void
    {
        foreach ([256, 512, 4096] as $value) {
            self::assertSame(
                [self::WITHOUT_CONTAINER],
                Memory::violations(self::remote() + ['install_memory_mb' => $value]),
            );
        }
    }

    public function test_a_remote_connector_with_an_invalid_value_reports_both_defects(): void
    {
        self::assertSame(
            [self::WITHOUT_CONTAINER, self::INVALID],
            Memory::violations(self::remote() + ['install_memory_mb' => 255]),
        );

        self::assertSame(
            [self::WITHOUT_CONTAINER, self::INVALID],
            Memory::violations(self::remote() + ['install_memory_mb' => 4097]),
        );
    }

    public function test_a_descriptor_with_no_mode_falls_back_to_the_container_it_carries(): void
    {
        self::assertSame([], Memory::violations([
            'install_image' => 'ghcr.io/example/notes:1',
            'install_memory_mb' => 512,
        ]));

        self::assertSame([], Memory::violations([
            'install_command' => 'npx -y example-notes-mcp@1.0.0',
            'install_memory_mb' => 512,
        ]));

        self::assertSame([self::INVALID], Memory::violations([
            'install_image' => 'ghcr.io/example/notes:1',
            'install_memory_mb' => 128,
        ]));

        self::assertSame([self::INVALID], Memory::violations([
            'install_command' => 'npx -y example-notes-mcp@1.0.0',
            'install_memory_mb' => 5000,
        ]));

        self::assertSame([self::WITHOUT_CONTAINER], Memory::violations([
            'install_memory_mb' => 512,
        ]));
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            [self::INVALID_IT],
            Memory::violations(self::image() + ['install_memory_mb' => 255], 'it'),
        );

        self::assertSame(
            [self::WITHOUT_CONTAINER_IT],
            Memory::violations(self::remote() + ['install_memory_mb' => 512], 'it'),
        );

        self::assertSame(
            [self::WITHOUT_CONTAINER_IT, self::INVALID_IT],
            Memory::violations(self::remote() + ['install_memory_mb' => 255], 'it'),
        );
    }

    public function test_every_locale_reads_its_own_catalog(): void
    {
        foreach (Labels::LOCALES as $locale) {
            $labels = Labels::for($locale);

            self::assertArrayHasKey('violation.memory_mb_invalid', $labels);
            self::assertArrayHasKey('violation.memory_without_runner', $labels);

            self::assertSame(
                [$labels['violation.memory_mb_invalid']],
                Memory::violations(self::image() + ['install_memory_mb' => 255], $locale),
            );

            self::assertSame(
                [$labels['violation.memory_without_runner']],
                Memory::violations(self::remote() + ['install_memory_mb' => 512], $locale),
            );

            self::assertSame(
                [$labels['violation.memory_without_runner'], $labels['violation.memory_mb_invalid']],
                Memory::violations(self::remote() + ['install_memory_mb' => 255], $locale),
            );
        }
    }

    public function test_the_form_labels_describe_the_memory_in_both_locales(): void
    {
        foreach (Labels::LOCALES as $locale) {
            $labels = Labels::for($locale);

            self::assertArrayHasKey('install.memory_mb.label', $labels);
            self::assertArrayHasKey('install.memory_mb.helper', $labels);
            self::assertNotSame('', $labels['install.memory_mb.label']);
            self::assertNotSame('', $labels['install.memory_mb.helper']);
        }

        $english = Labels::for('en');
        self::assertStringContainsString('512 MB', $english['install.memory_mb.helper']);
        self::assertStringContainsString('4096', $english['install.memory_mb.helper']);

        $italian = Labels::for('it');
        self::assertStringContainsString('512 MB', $italian['install.memory_mb.helper']);
    }

    public function test_a_form_value_is_normalized_to_the_number_the_descriptor_reads(): void
    {
        self::assertSame(512, Memory::normalize(512));
        self::assertSame(512, Memory::normalize('512'));
        self::assertSame(1024, Memory::normalize(' 1024 '));
        self::assertNull(Memory::normalize(null));
        self::assertNull(Memory::normalize(''));
        self::assertNull(Memory::normalize('   '));
        self::assertNull(Memory::normalize('300.5'));
        self::assertNull(Memory::normalize('not a number'));
        self::assertNull(Memory::normalize(300.5));
    }

    public function test_a_normalized_form_value_is_accepted(): void
    {
        self::assertSame(
            [],
            Memory::violations(self::image() + ['install_memory_mb' => Memory::normalize(' 1024 ')]),
        );
    }

    public function test_the_install_section_offers_a_memory_field(): void
    {
        foreach ([
            ConnectorSchemaConfig::make(),
            ConnectorSchemaConfig::make()->offersState(),
        ] as $config) {
            $field = self::memoryField(InstallSection::make($config));

            self::assertInstanceOf(TextInput::class, $field);
            self::assertSame('install_memory_mb', $field->getName());
        }
    }

    public function test_the_memory_field_stores_the_number_the_descriptor_reads(): void
    {
        $field = self::memoryField(InstallSection::make(ConnectorSchemaConfig::make()));

        self::assertInstanceOf(TextInput::class, $field);

        $property = new ReflectionProperty($field, 'dehydrateStateUsing');
        $dehydrate = $property->getValue($field);

        self::assertInstanceOf(\Closure::class, $dehydrate);
        self::assertSame(1024, $dehydrate('1024'));
        self::assertNull($dehydrate(''));
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame('install_memory_mb', Memory::KEY);
        self::assertSame(256, Memory::MIN);
        self::assertSame(4096, Memory::MAX);
    }

    private static function memoryField(Section $section): ?TextInput
    {
        foreach (self::childComponents($section) as $child) {
            if ($child instanceof TextInput && $child->getName() === 'install_memory_mb') {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<int, object>
     */
    private static function childComponents(object $component): array
    {
        $property = new ReflectionProperty($component, 'childComponents');
        $children = $property->getValue($component);
        $fields = $children['default'] ?? [];

        return is_array($fields) ? $fields : [];
    }
}
