<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The two field lists of the descriptor, `credential_parts` and
 * `provider_fields`, as form repeaters. See {@see CredentialFields} for the
 * shape and {@see Rules::fieldViolations()} for what is refused; the repeater
 * runs the same rules on save, so a form and a server path refuse the same
 * declarations.
 */
final class FieldsRepeater
{
    public static function credentialParts(ConnectorSchemaConfig $config): Repeater
    {
        return self::repeater('credential_parts', $config, withSuggestion: true)
            ->label($config->label('fields.parts.label'))
            ->helperText($config->label('fields.parts.helper'))
            ->visible(fn (Get $get): bool => in_array($get('auth_kind'), ['bearer', 'oauth2'], true))
            // A key is the field the person types: a connector that
            // authenticates with one declares it.
            ->minItems(fn (Get $get): int => $get('auth_kind') === 'bearer' ? 1 : 0)
            ->required(fn (Get $get): bool => $get('auth_kind') === 'bearer');
    }

    public static function providerFields(ConnectorSchemaConfig $config): Repeater
    {
        return self::repeater('provider_fields', $config, withSuggestion: false)
            ->label($config->label('fields.provider.label'))
            ->helperText($config->label('fields.provider.helper'))
            ->visible(fn (Get $get): bool => $get('auth_kind') === 'oauth2');
    }

    private static function repeater(string $list, ConnectorSchemaConfig $config, bool $withSuggestion): Repeater
    {
        $schema = [
            TextInput::make('key')
                ->label($config->label('fields.key.label'))
                ->helperText($config->label('fields.key.helper'))
                ->required()
                ->regex(CredentialFields::KEY_REGEX),
            TextInput::make('label')
                ->label($config->label('fields.label.label'))
                ->required(),
            Textarea::make('help')
                ->label($config->label('fields.help.label'))
                ->helperText($config->label('fields.help.helper'))
                ->rows(2)
                ->required()
                ->columnSpanFull(),
            Toggle::make('secret')
                ->label($config->label('fields.secret.label'))
                ->default(true),
            Toggle::make('optional')
                ->label($config->label('fields.optional.label'))
                ->default(false),
        ];

        if ($withSuggestion) {
            $schema[] = Fieldset::make($config->label('fields.suggest.label'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('suggest.url')
                        ->label($config->label('fields.suggest.url.label'))
                        ->helperText($config->label('fields.suggest.url.helper'))
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    Select::make('suggest.method')
                        ->label($config->label('fields.suggest.method.label'))
                        ->options(array_combine(CredentialFields::SUGGESTION_METHODS, CredentialFields::SUGGESTION_METHODS))
                        ->default('GET')
                        ->native(false)
                        ->live(),
                    TextInput::make('suggest.value_at')
                        ->label($config->label('fields.suggest.value_at.label'))
                        ->helperText($config->label('fields.suggest.value_at.helper'))
                        ->required(fn (Get $get): bool => trim((string) $get('suggest.url')) !== ''),
                    Textarea::make('suggest.body')
                        ->label($config->label('fields.suggest.body.label'))
                        ->rows(3)
                        ->rule('nullable')
                        ->rule('json')
                        // A body stored as data is shown as the JSON it is sent as.
                        ->formatStateUsing(fn (mixed $state): ?string => is_array($state)
                            ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                            : $state)
                        ->visible(fn (Get $get): bool => $get('suggest.method') === 'POST')
                        ->columnSpanFull(),
                ]);
        }

        return Repeater::make($list)
            ->schema($schema)
            ->columns(2)
            ->columnSpanFull()
            ->addActionLabel($config->label('fields.add'))
            ->default([])
            // Stored as the list the runtime reads, never as a repeater's
            // keyed items with an empty suggestion in each.
            ->dehydrateStateUsing(fn (mixed $state): ?array => CredentialFields::normalize($state))
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $list, $config): void {
                $descriptor = [
                    'auth_kind' => $get('auth_kind'),
                    $list => CredentialFields::normalize($value),
                ];
                foreach (Rules::fieldViolations($descriptor) as $violation) {
                    if ($violation['list'] === $list && $violation['code'] !== Rules::VIOLATION_CREDENTIAL_FIELDS_REQUIRED) {
                        $fail(Rules::fieldViolationMessage($violation, $config->getLocale()));
                    }
                }
            });
    }
}
