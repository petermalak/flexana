<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use App\Models\ClassType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Details')
                    ->description('Title, description, and how this package is categorized.')
                    ->icon(Heroicon::OutlinedGift)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. 10-Class Yoga Pack'),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->placeholder('Describe what’s included and who it’s for.'),
                        Forms\Components\Select::make('class_type_id')
                            ->label('Class format')
                            ->relationship('classType', 'name')
                            ->searchable()
                            ->preload()
                            ->hidden(true),
                        Forms\Components\Select::make('service_type')
                            ->label('Category')
                            ->options([
                                'Yoga' => 'Yoga',
                                'Reformer Pilates' => 'Reformer Pilates',
                            ])
                            ->searchable()
                            ->required()
                            ->helperText('Product category for app filtering (Yoga or Reformer Pilates)'),
                    ])->columns(2),
                Components\Section::make('Sessions & Pricing')
                    ->description('Number of sessions, price, and discount. Used sessions are tracked automatically.')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema([
                        Forms\Components\TextInput::make('total_sessions')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('used_sessions')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('This is automatically tracked'),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('discount')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->required(),
                    ])->columns(4),
                Components\Section::make('Settings')
                    ->description('Purchase expiry: 30-day unlimited uses days (not calendar months). Other packages use months.')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first in the mobile app package list.'),
                        Forms\Components\TextInput::make('package_duration_days')
                            ->label('Package duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('For 30-day unlimited: set to 30. Expiry = purchase date + exactly this many days.'),
                        Forms\Components\TextInput::make('package_duration')
                            ->label('Package duration (months)')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Used when duration (days) is empty. Expiry = purchase date + calendar months.'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->searchable()
                            ->default('active')
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('classType.name')
                    ->label('Class format')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Yoga' => 'success',
                        'Reformer Pilates' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Total')
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_sessions')
                    ->label('Used')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_sessions')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record) => $record->total_sessions - ($record->used_sessions ?? 0))
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->money()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('package_duration_days')
                    ->label('Duration (days)')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('package_duration')
                    ->label('Duration (months)')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('class_type_id')
                    ->label('Class format')
                    ->relationship('classType', 'name'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePackages::route('/'),
        ];
    }
}

