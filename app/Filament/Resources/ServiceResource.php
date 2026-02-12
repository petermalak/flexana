<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(4),
                    ])->columns(2),
                Components\Section::make('Pricing & Capacity')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('duration')
                            ->numeric()
                            ->label('Duration (seconds)')
                            ->default(1800)
                            ->required(),
                        Forms\Components\TextInput::make('min_capacity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('max_capacity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                    ])->columns(4),
                Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Service Type (Category)')
                            ->options([
                                'Yoga' => 'Yoga',
                                'Reformer Pilates' => 'Reformer Pilates',
                            ])
                            ->searchable()
                            ->helperText('Select the category for this service'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'visible' => 'Visible',
                                'hidden' => 'Hidden',
                            ])
                            ->searchable()
                            ->default('visible')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => gmdate('H:i', $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_capacity')
                    ->label('Min')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('max_capacity')
                    ->label('Max')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category_id')
                    ->label('Category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Yoga' => 'success',
                        'Reformer Pilates' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'visible',
                        'danger' => 'hidden',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Service Type')
                    ->options([
                        'Yoga' => 'Yoga',
                        'Reformer Pilates' => 'Reformer Pilates',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageServices::route('/'),
        ];
    }
}

