<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffOffDayResource\Pages;
use App\Infrastructure\Persistence\Eloquent\StaffOffDayModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class StaffOffDayResource extends Resource
{
    protected static ?string $model = StaffOffDayModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-x-circle';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Staff off days';

    protected static ?string $modelLabel = 'Off day';

    protected static ?string $pluralModelLabel = 'Staff off days';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Staff & Date')
                    ->description('Select the staff member(s) and the date range they will be off.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        Forms\Components\Toggle::make('apply_to_all_staff')
                            ->label('Apply to all staff')
                            ->helperText('Turn on to create this off day for every staff member.')
                            ->default(false)
                            ->live(),
                        Forms\Components\Select::make('staff_id')
                            ->label('Staff member')
                            ->relationship('staff', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => ! $get('apply_to_all_staff'))
                            ->disabled(fn (Get $get) => $get('apply_to_all_staff')),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('From date')
                            ->required()
                            ->default(now())
                            ->helperText('First day off (inclusive)'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Until date')
                            ->required()
                            ->default(now())
                            ->helperText('Last day off (inclusive)'),
                    ])->columns(3),
                Components\Section::make('Details')
                    ->description('Reason and time range for the off day.')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
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
                            ->rows(3)
                            ->placeholder('Additional notes or details'),
                    ])->columns(2),
                Components\Section::make('Time Range')
                    ->description('Set if this is a partial day off (e.g., morning only).')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
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
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),
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
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('time_range')
                    ->label('Time')
                    ->getStateUsing(function (StaffOffDayModel $record) {
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
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'vacation' => 'Vacation',
                        'sick' => 'Sick Leave',
                        'personal' => 'Personal',
                        'holiday' => 'Holiday',
                        'training' => 'Training',
                        'other' => 'Other',
                    ]),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn ($query, $date) => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn ($query, $date) => $query->whereDate('date', '<=', $date),
                            );
                    }),
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
            'index' => Pages\ManageStaffOffDays::route('/'),
        ];
    }
}
