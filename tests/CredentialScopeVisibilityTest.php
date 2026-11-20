<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The install section asks where a credential is kept for every auth kind that
 * actually carries one: a bearer token, an OAuth2 token, and a sign-in made in
 * the connector's own browser. A connector that authenticates nobody has no
 * scope to choose, and how the credential is delivered is a bearer/OAuth2
 * concern, not a browser one.
 */
final class CredentialScopeVisibilityTest extends TestCase
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

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function credentialScopes(): iterable
    {
        yield 'bearer' => ['bearer', true];
        yield 'oauth2' => ['oauth2', true];
        yield 'browser' => ['browser', true];
        yield 'none' => ['none', false];
    }

    #[DataProvider('credentialScopes')]
    public function test_the_credential_scope_follows_the_auth_kind(string $authKind, bool $visible): void
    {
        $field = self::field('credential_scope');

        self::assertSame(
            $visible,
            self::isVisibleFor($field, ['auth_kind' => $authKind]),
            "credential_scope visibility for auth_kind {$authKind}",
        );
    }

    public function test_the_credential_delivery_stays_hidden_for_a_browser_sign_in(): void
    {
        $field = self::field('credential_delivery');

        self::assertFalse(
            self::isVisibleFor($field, ['auth_kind' => 'browser']),
            'credential_delivery must stay hidden for auth_kind browser',
        );
        self::assertTrue(
            self::isVisibleFor($field, ['auth_kind' => 'bearer']),
            'credential_delivery must be visible for auth_kind bearer',
        );
    }

    private static function field(string $name): Select
    {
        foreach (self::childComponents(InstallSection::make(ConnectorSchemaConfig::make())) as $child) {
            if ($child instanceof Select && $child->getName() === $name) {
                return $child;
            }
        }

        self::fail("the install section has no {$name} field");
    }

    /**
     * The unit tests have no mounted form for Filament to resolve `Get`
     * against, so read the stored closure and hand it the form state directly.
     *
     * @param  array<string, mixed>  $state
     */
    private static function isVisibleFor(Component $field, array $state): bool
    {
        $property = new ReflectionProperty($field, 'isVisible');
        $condition = $property->getValue($field);

        if (is_bool($condition)) {
            return $condition;
        }

        return (bool) $condition(self::get($state));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function get(array $state): Get
    {
        return new class($state) extends Get {
            /**
             * @param  array<string, mixed>  $state
             */
            public function __construct(private array $state) {}

            public function __invoke(string|Component $path = '', bool $isAbsolute = false): mixed
            {
                return $this->state[$path] ?? null;
            }
        };
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
