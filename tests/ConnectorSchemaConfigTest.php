<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use PHPUnit\Framework\TestCase;

/**
 * The fluent config decides which install modes an app offers (marketplace: 4
 * incl. 'none'; control-plane will use 3), the label locale, the section-level
 * visibility gate, and whether the OPTIONAL strict cross-field rules are on.
 * Strict rules are OFF by default so adopting the package changes no app's
 * behavior — a later milestone (M-CONN-GUARD-1) opts in.
 */
final class ConnectorSchemaConfigTest extends TestCase
{
    public function test_defaults_offer_all_four_install_modes_including_none(): void
    {
        $config = ConnectorSchemaConfig::make();

        self::assertSame(['remote_url', 'command', 'image', 'none'], $config->getInstallModes());
        self::assertTrue($config->offersNoneMode());
    }

    public function test_default_locale_is_english(): void
    {
        self::assertSame('en', ConnectorSchemaConfig::make()->getLocale());
    }

    public function test_without_none_mode_drops_only_none_and_keeps_order(): void
    {
        $config = ConnectorSchemaConfig::make()->withoutNoneMode();

        self::assertSame(['remote_url', 'command', 'image'], $config->getInstallModes());
        self::assertFalse($config->offersNoneMode());
    }

    public function test_install_modes_can_be_set_explicitly_and_order_is_preserved(): void
    {
        $config = ConnectorSchemaConfig::make()->installModes(['image', 'command']);

        self::assertSame(['image', 'command'], $config->getInstallModes());
        self::assertFalse($config->offersNoneMode());
    }

    public function test_install_mode_options_are_filtered_and_ordered_by_config(): void
    {
        $all = ConnectorSchemaConfig::make()->installModeOptions();
        self::assertSame(['remote_url', 'command', 'image', 'none'], array_keys($all));
        // Exact marketplace labels (English catalog) — behavior-preserving.
        self::assertSame('Remote MCP (hosted URL — nothing to install)', $all['remote_url']);
        self::assertSame('None / clone the repo (legacy / content)', $all['none']);

        $three = ConnectorSchemaConfig::make()->withoutNoneMode()->installModeOptions();
        self::assertSame(['remote_url', 'command', 'image'], array_keys($three));
        self::assertArrayNotHasKey('none', $three);
    }

    public function test_strict_cross_field_rules_are_off_by_default(): void
    {
        self::assertFalse(ConnectorSchemaConfig::make()->isStrictCrossField());
    }

    public function test_strict_cross_field_can_be_enabled(): void
    {
        self::assertTrue(ConnectorSchemaConfig::make()->strictCrossField()->isStrictCrossField());
        self::assertFalse(ConnectorSchemaConfig::make()->strictCrossField(false)->isStrictCrossField());
    }

    public function test_section_visibility_defaults_to_null_and_is_settable(): void
    {
        self::assertNull(ConnectorSchemaConfig::make()->getSectionVisibility());

        $gate = static fn (): bool => true;
        self::assertSame($gate, ConnectorSchemaConfig::make()->visibleWhen($gate)->getSectionVisibility());
    }

    public function test_locale_switches_the_catalog(): void
    {
        $it = ConnectorSchemaConfig::make()->locale('it');
        self::assertSame('it', $it->getLocale());
        // The Italian catalog must resolve to non-empty strings for every mode.
        foreach ($it->installModeOptions() as $key => $label) {
            self::assertNotSame('', $label, "empty IT label for install mode {$key}");
        }
    }

    public function test_the_config_is_immutable_fluent(): void
    {
        $base = ConnectorSchemaConfig::make();
        $mutated = $base->withoutNoneMode()->strictCrossField()->locale('it');

        // The original is untouched (each setter returns a fresh instance).
        self::assertSame(['remote_url', 'command', 'image', 'none'], $base->getInstallModes());
        self::assertFalse($base->isStrictCrossField());
        self::assertSame('en', $base->getLocale());

        self::assertSame(['remote_url', 'command', 'image'], $mutated->getInstallModes());
        self::assertTrue($mutated->isStrictCrossField());
        self::assertSame('it', $mutated->getLocale());
    }
}
