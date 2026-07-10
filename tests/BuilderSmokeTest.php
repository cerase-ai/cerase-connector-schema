<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\InstallSection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The Section builders wrap Filament v5 components, which need a booted
 * Filament/Laravel app to instantiate — that behavioral coverage lives in the
 * CONSUMING apps' suites (the marketplace publisher-form tests stay green,
 * proving byte-for-byte parity). Here we only guarantee the builder files
 * parse and expose the expected static `make(ConnectorSchemaConfig): Section`
 * entrypoint, so a syntax/signature regression fails the package's own CI.
 */
final class BuilderSmokeTest extends TestCase
{
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
}
