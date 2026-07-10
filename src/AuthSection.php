<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The shared "Authentication" section of the connector descriptor: how users
 * connect this connector (auth kind, OAuth registration/provider, connect
 * instructions). The repo's cerase.json stays canonical at install — these
 * fields are the discovery-time declaration.
 *
 * Field names are part of the shared descriptor contract (`auth_kind`,
 * `auth_registration`, `auth_provider`, `auth_instructions`); labels come from
 * the configured locale, and the section-level visibility gate + the OPTIONAL
 * strict `auth_provider`-required-for-oauth2 rule come from the config.
 */
final class AuthSection
{
    public static function make(ConnectorSchemaConfig $config): Section
    {
        // auth_provider is only meaningful for OAuth. It is required-for-oauth2
        // ONLY under the opt-in strict cross-field rules (off by default, so
        // the marketplace's current behavior — provider optional — is kept).
        $authProvider = TextInput::make('auth_provider')
            ->label($config->label('auth.provider.label'))
            ->helperText($config->label('auth.provider.helper'))
            ->visible(fn (Get $get): bool => $get('auth_kind') === 'oauth2');

        if ($config->isStrictCrossField()) {
            $authProvider->required(fn (Get $get): bool => $get('auth_kind') === 'oauth2');
        }

        $section = Section::make($config->label('auth.title'))
            ->description($config->label('auth.description'))
            ->columns(2)
            ->schema([
                Select::make('auth_kind')
                    ->label($config->label('auth.kind.label'))
                    ->options($config->options('auth.kind.options'))
                    ->default('none')
                    ->required()
                    ->native(false)
                    ->live(),
                Select::make('auth_registration')
                    ->label($config->label('auth.registration.label'))
                    ->options($config->options('auth.registration.options'))
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('auth_kind') === 'oauth2'),
                $authProvider,
                Textarea::make('auth_instructions')
                    ->label($config->label('auth.instructions.label'))
                    ->helperText($config->label('auth.instructions.helper'))
                    ->rows(5)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => in_array($get('auth_kind'), ['bearer', 'oauth2'], true)),
            ]);

        if (($gate = $config->getSectionVisibility()) !== null) {
            $section->visible($gate);
        }

        return $section;
    }
}
