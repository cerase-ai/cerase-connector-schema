<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The shared "Install" section of the connector descriptor: how the
 * control-plane installs the connector directly from the registry (no git
 * clone). The install mode reveals only its matching field, and the credential
 * fields appear only when the connector authenticates.
 *
 * The install command / image are format-validated at the publishing boundary
 * via {@see Rules} (shell-metachar block / OCI ref). The offered install
 * modes, labels, section visibility, and the OPTIONAL strict
 * `credential_env`-required-for-env rule come from the config; every field
 * name is part of the shared descriptor contract.
 */
final class InstallSection
{
    public static function make(ConnectorSchemaConfig $config): Section
    {
        // credential_env is required-for-delivery=env ONLY under the opt-in
        // strict cross-field rules (off by default → marketplace behavior kept).
        $credentialEnv = TextInput::make('credential_env')
            ->label($config->label('credential.env.label'))
            ->helperText($config->label('credential.env.helper'))
            ->visible(fn (Get $get): bool => $get('credential_delivery') === 'env');

        if ($config->isStrictCrossField()) {
            $credentialEnv->required(fn (Get $get): bool => $get('credential_delivery') === 'env');
        }

        $fields = [
                Select::make('install_mode')
                    ->label($config->label('install.mode.label'))
                    ->options($config->installModeOptions())
                    ->native(false)
                    ->live()
                    ->columnSpanFull()
                    ->helperText($config->label('install.mode.helper')),
                TextInput::make('install_remote_url')
                    ->label($config->label('install.remote_url.label'))
                    ->url()
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('install_mode') === 'remote_url')
                    ->required(fn (Get $get): bool => $get('install_mode') === 'remote_url'),
                TextInput::make('install_command')
                    ->label($config->label('install.command.label'))
                    ->helperText($config->label('install.command.helper'))
                    ->columnSpanFull()
                    // This string is served to the sandboxed runner — block
                    // shell metacharacters at the publishing boundary.
                    ->rule(Rules::commandRule())
                    ->validationMessages(['regex' => $config->label('install.command.regex_message')])
                    ->visible(fn (Get $get): bool => $get('install_mode') === 'command')
                    ->required(fn (Get $get): bool => $get('install_mode') === 'command'),
                TextInput::make('install_image')
                    ->label($config->label('install.image.label'))
                    ->helperText($config->label('install.image.helper'))
                    ->columnSpanFull()
                    // An OCI image ref — reject anything that could break out of
                    // the docker argument.
                    ->rule(Rules::imageRule())
                    ->validationMessages(['regex' => $config->label('install.image.regex_message')])
                    ->visible(fn (Get $get): bool => $get('install_mode') === 'image')
                    ->required(fn (Get $get): bool => $get('install_mode') === 'image'),
                TagsInput::make('install_env_passthrough')
                    ->label($config->label('install.env_passthrough.label'))
                    ->helperText($config->label('install.env_passthrough.helper'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => in_array($get('install_mode'), ['command', 'image'], true)),
                Select::make('credential_delivery')
                    ->label($config->label('credential.delivery.label'))
                    ->options($config->options('credential.delivery.options'))
                    ->native(false)
                    ->live()
                    ->visible(fn (Get $get): bool => in_array($get('auth_kind'), ['bearer', 'oauth2'], true)),
                $credentialEnv,
                Select::make('credential_scope')
                    ->label($config->label('credential.scope.label'))
                    ->options($config->options('credential.scope.options'))
                    ->default('user')
                    ->native(false)
                    ->visible(fn (Get $get): bool => in_array($get('auth_kind'), ['bearer', 'oauth2'], true)),
                TagsInput::make('scopes')
                    ->label($config->label('scopes.label'))
                    ->helperText($config->label('scopes.helper'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('auth_kind') === 'oauth2'),
                Repeater::make('credential_files')
                    ->label($config->label('credential.files.label'))
                    ->helperText($config->label('credential.files.helper'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('credential_delivery') === 'file')
                    ->schema([
                        TextInput::make('path')->required()->label($config->label('credential.files.path.label')),
                        Textarea::make('template')->required()->rows(3)->label($config->label('credential.files.template.label')),
                    ])
                    ->default([]),
        ];

        // The control-plane locks the descriptor after creation.
        // OFF by default → the marketplace keeps every field editable.
        if (($disabled = $config->getDisabledWhen()) !== null) {
            foreach ($fields as $field) {
                $field->disabled($disabled);
            }
        }

        $section = Section::make($config->label('install.title'))
            ->description($config->label('install.description'))
            ->columns(2)
            ->schema($fields);

        if (($gate = $config->getSectionVisibility()) !== null) {
            $section->visible($gate);
        }

        return $section;
    }
}
