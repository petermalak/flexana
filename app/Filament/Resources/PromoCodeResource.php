<?php

namespace App\Filament\Resources;

use App\Domain\Promo\Enums\PromoApplicableType;
use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Collection;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Promo Code';

    protected static ?string $pluralModelLabel = 'Promo Codes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255)
                            ->placeholder('Internal name (optional)'),
                        Forms\Components\TextInput::make('percent_discount')
                            ->label('Discount (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required()
                            ->suffix('%'),
                    ])->columns(2),
                Components\Section::make('Where this code applies')
                    ->description('Control whether this promo works on package purchases, drop-in bookings, or both.')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->schema([
                        Forms\Components\Select::make('applicable_to')
                            ->label('Applies to')
                            ->options(static::applicableTypeOptions())
                            ->default(PromoApplicableType::Both->value)
                            ->required()
                            ->native(false)
                            ->helperText(fn (?string $state): string => (string) (
                                config('promo.applicable_type_descriptions')[$state ?? PromoApplicableType::Both->value]
                                ?? 'Choose which checkout flow accepts this code.'
                            ))
                            ->live(),
                    ]),
                Components\Section::make('Validity')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid from'),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label('Valid until'),
                        Forms\Components\TextInput::make('usage_limit_per_user')
                            ->label('Max uses per customer')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->placeholder('Unlimited')
                            ->helperText('Each customer can apply this code at most this many times.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('percent_discount')
                    ->label('Discount %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\SelectColumn::make('applicable_to')
                    ->label('Applies to')
                    ->options(static::applicableTypeOptions())
                    ->selectablePlaceholder(false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Total uses')
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_limit_per_user')
                    ->label('Per customer')
                    ->placeholder('∞')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('applicable_to')
                    ->label('Applies to')
                    ->options(static::applicableTypeOptions()),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('setApplicableTo')
                        ->label('Set applies to')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->form([
                            Forms\Components\Select::make('applicable_to')
                                ->label('Applies to')
                                ->options(static::applicableTypeOptions())
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (PromoCode $record) => $record->update([
                                'applicable_to' => $data['applicable_to'],
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function applicableTypeOptions(): array
    {
        return config('promo.applicable_types', []);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
