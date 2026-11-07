<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Cerase\ConnectorSchema\Labels;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Livewire\Component as Livewire;
use PHPUnit\Framework\TestCase;

/**
 * A connector published by somebody else is held to the Marketplace rules: its
 * image is pinned by digest and its remote URL is https on a public host. The
 * box keeps allowing a private address for a connector on its own network.
 */
final class PublishedByOthersTest extends TestCase
{
    private ?Container $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();

        $container = new Container();
        $container->instance('translator', new Translator(new ArrayLoader(), 'en'));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_a_published_connector_image_must_be_pinned_by_digest(): void
    {
        $field = $this->field(ConnectorSchemaConfig::make()->publishedByOthers(), 'install_image');

        self::assertContains(
            ConnectorSchemaConfig::make()->label('install.image.digest_message'),
            $this->errorsFor($field, 'ghcr.io/acme/x:latest'),
        );
    }

    public function test_a_published_connector_image_pinned_by_digest_is_accepted(): void
    {
        $field = $this->field(ConnectorSchemaConfig::make()->publishedByOthers(), 'install_image');

        self::assertSame(
            [],
            $this->errorsFor($field, 'ghcr.io/acme/x@sha256:'.str_repeat('a', 64)),
        );
    }

    public function test_a_published_connector_remote_url_must_be_public_https(): void
    {
        $field = $this->field(ConnectorSchemaConfig::make()->publishedByOthers(), 'install_remote_url');

        self::assertContains(
            ConnectorSchemaConfig::make()->label('install.remote_url.public_message'),
            $this->errorsFor($field, 'http://10.0.0.5:3000/mcp'),
        );
    }

    public function test_a_published_connector_remote_url_on_a_public_https_host_is_accepted(): void
    {
        $field = $this->field(ConnectorSchemaConfig::make()->publishedByOthers(), 'install_remote_url');

        self::assertSame(
            [],
            $this->errorsFor($field, 'https://mcp.example.com/mcp'),
        );
    }

    public function test_a_connector_not_published_by_others_accepts_an_unpinned_image_and_a_private_http_url(): void
    {
        $config = ConnectorSchemaConfig::make();

        self::assertSame(
            [],
            $this->errorsFor($this->field($config, 'install_image'), 'ghcr.io/acme/x:latest'),
        );
        self::assertSame(
            [],
            $this->errorsFor($this->field($config, 'install_remote_url'), 'http://10.0.0.5:3000/mcp'),
        );
    }

    public function test_the_published_by_others_labels_exist_in_every_locale(): void
    {
        $keys = ['install.image.digest_message', 'install.remote_url.public_message'];

        foreach (Labels::LOCALES as $locale) {
            foreach ($keys as $key) {
                $label = Labels::for($locale)[$key] ?? null;

                self::assertIsString($label, "{$key} in {$locale}");
                self::assertNotSame('', trim($label), "{$key} is empty in {$locale}");
            }
        }

        foreach ($keys as $key) {
            self::assertNotSame(
                Labels::for('en')[$key],
                Labels::for('it')[$key],
                "{$key} must have a distinct Italian sentence",
            );
        }
    }

    private function field(ConnectorSchemaConfig $config, string $name): TextInput
    {
        $mode = match ($name) {
            'install_image' => 'image',
            'install_remote_url' => 'remote_url',
            default => null,
        };

        $livewire = new class($mode) extends Livewire implements HasSchemas
        {
            use InteractsWithSchemas;

            public function __construct(public ?string $install_mode) {}
        };

        $schema = Schema::make($livewire);
        $section = InstallSection::make($config);
        $schema->components([$section]);
        $schema->getComponents();

        foreach ($section->getChildSchema()->getComponents(withHidden: true) as $field) {
            if ($field->getName() === $name) {
                self::assertInstanceOf(TextInput::class, $field);

                return $field;
            }
        }

        self::fail("The Install section has no {$name} field.");
    }

    /**
     * @return list<string>
     */
    private function errorsFor(TextInput $field, string $value): array
    {
        $container = Container::getInstance();
        $factory = new Factory($container->make('translator'), $container);

        return $factory->make(
            [$field->getName() => $value],
            [$field->getName() => $field->getValidationRules()],
            $field->getValidationMessages(),
        )->errors()->all();
    }
}
