<?php

namespace App\Filament\Support;

use App\Models\PublicFormSubmission;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Settings\SettingsItemCloner;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicFormsSettingsForm
{
    public static function definitionsRepeater(): Repeater
    {
        return Repeater::make('public_forms.definitions')
            ->label(__('admin.fields.public_forms'))
            ->helperText(__('admin.helpers.public_forms'))
            ->schema([
                Fieldset::make(__('admin.sections.public_form_identity'))
                    ->schema([
                        TextInput::make('key')
                            ->label(__('admin.fields.public_form_key'))
                            ->helperText(__('admin.helpers.public_form_key'))
                            ->hint(fn (Get $get): ?string => self::formHasSubmissions($get('key'))
                                ? __('admin.labels.public_form_key_locked')
                                : null)
                            ->disabled(fn (Get $get): bool => self::formHasSubmissions($get('key')))
                            ->dehydrated()
                            ->required()
                            ->maxLength(80)
                            ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                        TextInput::make('name')
                            ->label(__('admin.fields.public_form_name'))
                            ->helperText(__('admin.helpers.public_form_name'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (filled($get('key')) || blank($state)) {
                                    return;
                                }

                                $set('key', self::semanticKey($state));
                            })
                            ->required()
                            ->maxLength(120),
                        TextInput::make('heading')
                            ->label(__('admin.fields.public_form_heading'))
                            ->helperText(__('admin.helpers.public_form_heading'))
                            ->maxLength(160),
                        Select::make('display_mode_default')
                            ->label(__('admin.fields.public_form_display_mode'))
                            ->helperText(__('admin.helpers.public_form_display_mode'))
                            ->options(fn (): array => PublicFrontConfigRegistry::publicFormDisplayModeOptions())
                            ->default('modal')
                            ->native(false)
                            ->required(),
                        Toggle::make('enabled')
                            ->label(__('admin.fields.public_form_enabled'))
                            ->helperText(__('admin.helpers.public_form_enabled'))
                            ->default(false),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Fieldset::make(__('admin.sections.public_form_behavior'))
                    ->schema([
                        TextInput::make('submit_label')
                            ->label(__('admin.fields.public_form_submit_label'))
                            ->helperText(__('admin.helpers.public_form_submit_label'))
                            ->maxLength(80),
                        TextInput::make('success_message')
                            ->label(__('admin.fields.public_form_success_message'))
                            ->helperText(__('admin.helpers.public_form_success_message'))
                            ->maxLength(240)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('admin.fields.public_form_description'))
                            ->helperText(__('admin.helpers.public_form_description'))
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('settings.rate_limit_attempts')
                            ->label(__('admin.fields.public_form_rate_limit_attempts'))
                            ->helperText(__('admin.helpers.public_form_rate_limit_attempts'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(5),
                        TextInput::make('settings.rate_limit_decay_seconds')
                            ->label(__('admin.fields.public_form_rate_limit_decay_seconds'))
                            ->helperText(__('admin.helpers.public_form_rate_limit_decay_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(60)
                            ->maxValue(86400)
                            ->default(600),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Fieldset::make(__('admin.sections.public_form_fields_config'))
                    ->schema([
                        Builder::make('fields')
                            ->label(__('admin.fields.public_form_fields'))
                            ->helperText(__('admin.helpers.public_form_fields'))
                            ->blocks(self::fieldBlocks())
                            ->blockPickerColumns(2)
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->default([])
                            ->addActionLabel(__('admin.actions.add_public_form_field'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['key'] ?? __('admin.labels.untitled'))
            ->defaultItems(0)
            ->reorderable()
            ->collapsed()
            ->extraItemActions([
                Action::make('clonePublicForm')
                    ->label(__('admin.actions.clone_public_form'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->action(function (array $arguments, Repeater $component): void {
                        $state = $component->getState();
                        $itemKey = $arguments['item'] ?? null;

                        if ($itemKey === null || ! array_key_exists($itemKey, $state)) {
                            return;
                        }

                        $item = $component->getRawItemState($itemKey);

                        if (! is_array($item)) {
                            return;
                        }

                        $state[(string) Str::uuid()] = app(SettingsItemCloner::class)->clone(
                            item: $item,
                            collection: $state,
                            copySuffix: __('admin.labels.copy_suffix'),
                            overrides: ['enabled' => false],
                        );

                        $component->state($state);
                    }),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    /**
     * @param  array<string, mixed>  $publicForms
     * @return array<string, mixed>
     */
    public static function publicFormsForBuilder(array $publicForms): array
    {
        $publicForms['definitions'] = collect($publicForms['definitions'] ?? [])
            ->filter(fn (mixed $definition): bool => is_array($definition))
            ->map(function (array $definition): array {
                $definition['fields'] = collect($definition['fields'] ?? [])
                    ->filter(fn (mixed $field): bool => is_array($field))
                    ->map(fn (array $field): array => [
                        'type' => $field['type'] ?? 'text',
                        'data' => Arr::except($field, ['type']),
                    ])
                    ->values()
                    ->all();

                return $definition;
            })
            ->values()
            ->all();

        return $publicForms;
    }

    /**
     * @return array<Block>
     */
    private static function fieldBlocks(): array
    {
        return collect(PublicFrontConfigRegistry::publicFormFieldTypes())
            ->map(fn (string $type): Block => Block::make($type)
                ->label(__("admin.public_form_field_types.{$type}"))
                ->schema(self::fieldSchema($type))
                ->columns(3))
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private static function fieldSchema(string $type): array
    {
        $supportsTextLengths = in_array($type, ['text', 'email', 'phone', 'textarea', 'url'], true);
        $supportsOptions = in_array($type, ['select', 'checkbox'], true);

        return [
            TextInput::make('key')
                ->label(__('admin.fields.public_form_field_key'))
                ->helperText(__('admin.helpers.public_form_field_key'))
                ->required()
                ->maxLength(80)
                ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
            TextInput::make('label')
                ->label(__('admin.fields.public_form_field_label'))
                ->helperText(__('admin.helpers.public_form_field_label'))
                ->required()
                ->maxLength(120),
            Toggle::make('required')
                ->label(__('admin.fields.public_form_field_required'))
                ->helperText(__('admin.helpers.public_form_field_required'))
                ->default(false),
            TextInput::make('placeholder')
                ->label(__('admin.fields.public_form_field_placeholder'))
                ->helperText(__('admin.helpers.public_form_field_placeholder'))
                ->maxLength(160)
                ->visible($type !== 'checkbox'),
            TextInput::make('help_text')
                ->label(__('admin.fields.public_form_field_help_text'))
                ->helperText(__('admin.helpers.public_form_field_help_text'))
                ->maxLength(240),
            Select::make('validation_semantics')
                ->label(__('admin.fields.public_form_field_validation_semantics'))
                ->helperText(__('admin.helpers.public_form_field_validation_semantics'))
                ->options(fn (): array => PublicFrontConfigRegistry::publicFormValidationSemanticOptions())
                ->default('none')
                ->native(false),
            TextInput::make('min_length')
                ->label(__('admin.fields.public_form_field_min_length'))
                ->helperText(__('admin.helpers.public_form_field_min_length'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(5000)
                ->visible($supportsTextLengths),
            TextInput::make('max_length')
                ->label(__('admin.fields.public_form_field_max_length'))
                ->helperText(__('admin.helpers.public_form_field_max_length'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(5000)
                ->visible($supportsTextLengths),
            Repeater::make('options')
                ->label(__('admin.fields.public_form_field_options'))
                ->helperText(__('admin.helpers.public_form_field_options'))
                ->schema([
                    TextInput::make('value')
                        ->label(__('admin.fields.public_form_option_value'))
                        ->helperText(__('admin.helpers.public_form_option_value'))
                        ->required($type === 'select')
                        ->maxLength(80)
                        ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                    TextInput::make('label')
                        ->label(__('admin.fields.public_form_option_label'))
                        ->helperText(__('admin.helpers.public_form_option_label'))
                        ->required($type === 'select')
                        ->maxLength(120),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->cloneable()
                ->columns(2)
                ->visible($supportsOptions)
                ->columnSpanFull(),
        ];
    }

    private static function semanticKey(string $value): string
    {
        $key = Str::of($value)
            ->ascii()
            ->slug('_')
            ->lower()
            ->toString();

        if ($key === '' || ! preg_match('/^[a-z]/', $key)) {
            return 'form';
        }

        return $key;
    }

    private static function formHasSubmissions(mixed $key): bool
    {
        return filled($key)
            && PublicFormSubmission::query()->where('form_key', (string) $key)->exists();
    }
}
