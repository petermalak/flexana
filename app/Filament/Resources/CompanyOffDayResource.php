<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnlyResource;
use App\Filament\Resources\CompanyOffDayResource\Pages;
use App\Infrastructure\Persistence\Eloquent\CompanyOffDayModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class CompanyOffDayResource extends Resource
{
    use SuperAdminOnlyResource;

    protected static ?string $model = CompanyOffDayModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Company holidays';

    protected static ?string $modelLabel = 'Company holiday';

    protected static ?string $pluralModelLabel = 'Company holidays';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Holiday Details')
                    ->description('Company-wide holidays when all staff are off.')
                    ->icon(Heroicon::OutlinedBuildingOffice)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Holiday name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. New Year\'s Day, Christmas, Company Holiday'),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now())
                            ->helperText('The date of the company holiday'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Optional description or notes about this holiday'),
                    ])->columns(2),
                Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring yearly')
                            ->default(false)
                            ->helperText('If enabled, this holiday will repeat every year on the same date'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required()
                            ->helperText('Inactive holidays are ignored in scheduling'),
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
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_recurring')
                    ->boolean()
                    ->label('Recurring')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
            ])
            ->defaultSort('date', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_recurring')
                    ->label('Recurring'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            'index' => Pages\ManageCompanyOffDays::route('/'),
        ];
    }
}
