<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Filament\Actions;

class OffDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'offDays';

    protected static ?string $title = 'Off Days';

    protected static \BackedEnum|string|null $icon = Heroicon::OutlinedXCircle;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->helperText('The date the staff member will be unavailable'),
                Forms\Components\Select::make('reason')
                    ->options([
                        'vacation' => 'Vacation',
                        'sick' => 'Sick Leave',
                        'personal' => 'Personal',
                        'holiday' => 'Holiday',
                        'training' => 'Training',
                        'other' => 'Other',
                    ])
                    ->searchable()
                    ->helperText('Reason for the off day'),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->placeholder('Optional notes'),
                Forms\Components\Toggle::make('is_all_day')
                    ->label('All day')
                    ->default(true)
                    ->required()
                    ->live()
                    ->helperText('Uncheck to set specific time range'),
                Forms\Components\TimePicker::make('start_time')
                    ->label('Start time')
                    ->visible(fn (Get $get) => !$get('is_all_day'))
                    ->required(fn (Get $get) => !$get('is_all_day'))
                    ->seconds(false),
                Forms\Components\TimePicker::make('end_time')
                    ->label('End time')
                    ->visible(fn (Get $get) => !$get('is_all_day'))
                    ->required(fn (Get $get) => !$get('is_all_day'))
                    ->seconds(false)
                    ->after('start_time'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('reason')
                    ->colors([
                        'success' => 'vacation',
                        'warning' => 'sick',
                        'info' => 'personal',
                        'primary' => 'holiday',
                        'gray' => 'training',
                        'danger' => 'other',
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-'),
                Tables\Columns\TextColumn::make('time_range')
                    ->label('Time')
                    ->getStateUsing(function ($record) {
                        if ($record->is_all_day) {
                            return 'All day';
                        }
                        $start = $record->start_time ? $record->start_time->format('H:i') : '';
                        $end = $record->end_time ? $record->end_time->format('H:i') : '';
                        return $start && $end ? "{$start} - {$end}" : '-';
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'vacation' => 'Vacation',
                        'sick' => 'Sick Leave',
                        'personal' => 'Personal',
                        'holiday' => 'Holiday',
                        'training' => 'Training',
                        'other' => 'Other',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
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
            ->defaultSort('date', 'desc');
    }
}
