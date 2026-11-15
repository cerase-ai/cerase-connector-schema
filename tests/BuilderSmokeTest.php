<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The Section builders wrap Filament v5 components, which need a booted
 * Filament/Laravel app to instantiate — that behavioral coverage lives in the
 * CONSUMING apps' suites (the marketplace publisher-form tests stay green,
 * proving byte-for-byte parity). Here we only guarantee the builder files
 * parse and expose the expected static `make(ConnectorSchemaConfig): Section`
 * entrypoint, so a syntax/signature regression fails the package's own CI.
 *
 * A minimal translator lets the Install section build without a full app, so
 * the opt-in state fieldset can be checked for presence and shape.
 */
final class BuilderSmokeTest extends TestCase
{
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

    public function test_builders_load_and_expose_a_static_make(): void
    {
        foreach ([AuthSection::class, InstallSection::class] as $class) {
            self::assertTrue(class_exists($class), "{$class} did not load");

            $make = new ReflectionMethod($class, 'make');
            self::assertTrue($make->isStatic(), "{$class}::make must be static");
            self::assertTrue($make->isPublic(), "{$class}::make must be public");

            $params = $make->getParameters();
            self::assertCount(1, $params, "{$class}::make takes exactly the config");
            self::assertSame(
                \Cerase\ConnectorSchema\ConnectorSchemaConfig::class,
                (string) $params[0]->getType(),
                "{$class}::make must accept a ConnectorSchemaConfig",
            );
        }
    }

    public function test_the_field_repeaters_load_and_take_the_config(): void
    {
        foreach (['credentialParts', 'providerFields'] as $method) {
            $builder = new ReflectionMethod(\Cerase\ConnectorSchema\FieldsRepeater::class, $method);
            self::assertTrue($builder->isStatic() && $builder->isPublic(), "FieldsRepeater::{$method} must be public static");
            self::assertSame(
                \Cerase\ConnectorSchema\ConnectorSchemaConfig::class,
                (string) $builder->getParameters()[0]->getType(),
            );
            self::assertSame(\Filament\Forms\Components\Repeater::class, (string) $builder->getReturnType());
        }
    }

    public function test_the_state_fieldset_is_absent_by_default(): void
    {
        $section = InstallSection::make(ConnectorSchemaConfig::make());

        self::assertNull(self::stateFieldset($section));
    }

    public function test_the_state_fieldset_appears_when_the_config_offers_state(): void
    {
        $section = InstallSection::make(ConnectorSchemaConfig::make()->offersState());
        $fieldset = self::stateFieldset($section);

        self::assertNotNull($fieldset);

        $children = self::childComponents($fieldset);
        self::assertSame(
            ['path', 'max_mb', 'keep_running'],
            array_map(static fn (object $child): ?string => $child->getName(), $children),
        );
        self::assertInstanceOf(TextInput::class, $children[0]);
        self::assertInstanceOf(TextInput::class, $children[1]);
        self::assertInstanceOf(Toggle::class, $children[2]);
    }

    private static function stateFieldset(Section $section): Fieldset|Group|null
    {
        foreach (self::childComponents($section) as $child) {
            if (! $child instanceof Fieldset && ! $child instanceof Group) {
                continue;
            }

            $statePath = new ReflectionProperty($child, 'statePath');
            if ($statePath->getValue($child) === 'install_state') {
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
