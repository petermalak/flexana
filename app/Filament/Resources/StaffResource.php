<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\Staff;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('photo_path')
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory('staff-photos')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Select::make('role')
                            ->options([
                                'instructor' => 'Instructor',
                                'staff' => 'Staff',
                                'admin' => 'Admin',
                            ])
                            ->searchable()
                            ->default('instructor')
                            ->required(),
                    ])->columns(2),
                Components\Section::make('Services')
                    ->description('Assign services this staff member can provide.')
                    ->schema([
                        Forms\Components\Select::make('services')
                            ->relationship('services', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Select all services this staff member can teach/provide'),
                    ]),
                Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\TextInput::make('timezone')
                            ->default('UTC')
                            ->maxLength(60)
                            ->required(),
                        Forms\Components\TagsInput::make('skills')
                            ->placeholder('Add a skill'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'instructor',
                        'success' => 'staff',
                        'danger' => 'admin',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('services.name')
                    ->label('Services')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'instructor' => 'Instructor',
                        'staff' => 'Staff',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\Action::make('schedule')
                    ->label('Week schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->url(fn ($record) => static::getUrl('schedule', ['record' => $record]))
                    ->color('primary'),
                Actions\Action::make('edit_details')
                    ->label('Edit details')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
            ])
            ->recordUrl(fn ($record) => static::getUrl('schedule', ['record' => $record]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStaff::route('/'),
            'schedule' => Pages\ViewStaffSchedule::route('/{record}/schedule'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}

