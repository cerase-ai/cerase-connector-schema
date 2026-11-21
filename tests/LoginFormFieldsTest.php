<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\Login;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The auth_login group carries the steps a person follows, one textarea per
 * language, and how long the code it prints lasts. Each is labelled from the
 * configured locale's catalog.
 */
final class LoginFormFieldsTest extends TestCase
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

    public function test_the_login_group_holds_the_steps_and_code_seconds_fields_in_order(): void
    {
        self::assertSame(
            ['args', 'port', 'steps.it', 'steps.en', 'code_seconds'],
            array_map(
                static fn (Component $child): string => $child->getName(),
                self::childComponents(self::loginGroup()),
            ),
        );

        self::assertInstanceOf(Textarea::class, self::field('steps.it'));
        self::assertInstanceOf(Textarea::class, self::field('steps.en'));
        self::assertInstanceOf(TextInput::class, self::field('code_seconds'));
    }

    public function test_the_new_fields_carry_a_label_per_language(): void
    {
        foreach ([
            'steps.it' => 'login.steps_it.label',
            'steps.en' => 'login.steps_en.label',
            'code_seconds' => 'login.code_seconds.label',
        ] as $name => $key) {
            $en = self::label(self::field($name, 'en'), $name);
            $it = self::label(self::field($name, 'it'), $name);

            self::assertSame(
                ConnectorSchemaConfig::make()->locale('en')->label($key),
                $en,
                "{$name} label in English",
            );
            self::assertSame(
                ConnectorSchemaConfig::make()->locale('it')->label($key),
                $it,
                "{$name} label in Italian",
            );
            self::assertNotSame('', $en, "{$name} label in English is not empty");
            self::assertNotSame('', $it, "{$name} label in Italian is not empty");
            self::assertNotSame($en, $it, "{$name} label differs between English and Italian");
        }
    }

    private static function loginGroup(string $locale = 'it'): Group
    {
        foreach (self::childComponents(AuthSection::make(ConnectorSchemaConfig::make()->locale($locale))) as $child) {
            if ($child instanceof Group && $child->getStatePath(false) === Login::KEY) {
                return $child;
            }
        }

        self::fail('the auth section has no auth_login group');
    }

    private static function field(string $name, string $locale = 'it'): Field
    {
        foreach (self::childComponents(self::loginGroup($locale)) as $child) {
            if ($child instanceof Field && $child->getName() === $name) {
                return $child;
            }
        }

        self::fail("the auth_login group has no {$name} field");
    }

    private static function label(Field $field, string $name): string
    {
        $label = $field->getLabel();

        self::assertIsString($label, "{$name} has a string label");

        return $label;
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
