<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
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

        $fields = [
            Select::make('auth_kind')
                ->label($config->label('auth.kind.label'))
                ->options($config->options('auth.kind.options'))
                ->default('none')
                ->required()
                // Refuse a kind no consumer can connect yet, always — not only
                // under strict cross-field rules — with the same sentence the
                // server-side callers use. Wrapped in a closure that returns the
                // rule: Filament evaluates a closure handed to rule() itself,
                // resolving its parameters by name, and cannot resolve
                // $attribute, $value and $fail.
                ->rule(fn (): Closure => Rules::authKindRule($config->getLocale()))
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
        ];

        // How a sign-in in the connector's own browser runs: the arguments its
        // image takes and the port it serves the person's page on.
        $fields[] = Group::make()
            ->statePath(Login::KEY)
            ->columns(2)
            ->schema([
                TagsInput::make('args')
                    ->label($config->label('login.args.label'))
                    ->helperText($config->label('login.args.helper'))
                    ->required()
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $config): void {
                        $descriptor = [
                            'auth_kind' => $get('../auth_kind'),
                            'install_mode' => $get('../install_mode'),
                            'install_image' => $get('../install_image'),
                            'install_state' => $get('../install_state'),
                            'auth_login' => Login::normalize([
                                'args' => $value,
                                'port' => $get('port'),
                                'steps' => $get('steps'),
                                'code_seconds' => $get('code_seconds'),
                            ]),
                        ];
                        foreach (Login::violations($descriptor, $config->getLocale()) as $violation) {
                            $fail($violation);
                        }
                    }),
                TextInput::make('port')
                    ->label($config->label('login.port.label'))
                    ->helperText($config->label('login.port.helper'))
                    ->numeric(),
                Textarea::make('steps.it')
                    ->label($config->label('login.steps_it.label'))
                    ->helperText($config->label('login.steps.helper'))
                    ->rows(4)
                    ->columnSpanFull()
                    ->formatStateUsing(fn (mixed $state): mixed => is_array($state) ? implode("\n", array_map('strval', $state)) : $state),
                Textarea::make('steps.en')
                    ->label($config->label('login.steps_en.label'))
                    ->helperText($config->label('login.steps.helper'))
                    ->rows(4)
                    ->columnSpanFull()
                    ->formatStateUsing(fn (mixed $state): mixed => is_array($state) ? implode("\n", array_map('strval', $state)) : $state),
                TextInput::make('code_seconds')
                    ->label($config->label('login.code_seconds.label'))
                    ->helperText($config->label('login.code_seconds.helper'))
                    ->integer(),
            ])
            ->visible(fn (Get $get): bool => $get('auth_kind') === 'browser');

        // The control-plane locks the descriptor after creation.
        // OFF by default → the marketplace keeps every field editable.
        if (($disabled = $config->getDisabledWhen()) !== null) {
            foreach ($fields as $field) {
                $field->disabled($disabled);
            }
        }

        $section = Section::make($config->label('auth.title'))
            ->description($config->label('auth.description'))
            ->columns(2)
            ->schema($fields);

        if (($gate = $config->getSectionVisibility()) !== null) {
            $section->visible($gate);
        }

        return $section;
    }
}
