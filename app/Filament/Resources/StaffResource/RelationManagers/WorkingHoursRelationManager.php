<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Filament\Actions;

class WorkingHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Working hours';

    protected static ?string $modelLabel = 'Working hours';

    protected static \BackedEnum|string|null $icon = Heroicon::OutlinedClock;

    protected static ?string $description = 'Set weekly recurring availability (e.g. Monday 9:00–17:00). You can add multiple time ranges per day for split shifts.';

    private const WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('weekday')
                    ->label('Day of week')
                    ->options(collect(self::WEEKDAYS)->mapWithKeys(fn ($label, $key) => [(string) $key => $label])->all())
                    ->required()
                    ->searchable(),
                Forms\Components\TimePicker::make('starts_at')
                    ->label('Start time')
                    ->required()
                    ->seconds(false),
                Forms\Components\TimePicker::make('ends_at')
                    ->label('End time')
                    ->required()
                    ->seconds(false)
                    ->after('starts_at'),
            ]);
    }

    public function table(Table $table): Table
    {
        $weekdayNames = self::WEEKDAYS;

        return $table
            ->recordTitleAttribute('weekday')
            ->columns([
                Tables\Columns\TextColumn::make('weekday')
                    ->label('Day')
                    ->formatStateUsing(fn (?string $state) => $state ? ($weekdayNames[(int) $state] ?? ucfirst((string) $state)) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('End')
                    ->time('H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add working hours'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('weekday')
            ->emptyStateHeading('No working hours set')
            ->emptyStateDescription('Add recurring weekly availability so appointments can be scheduled within these times.')
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
