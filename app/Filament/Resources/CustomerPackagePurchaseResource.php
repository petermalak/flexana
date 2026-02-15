<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerPackagePurchaseResource\Pages;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class CustomerPackagePurchaseResource extends Resource
{
    protected static ?string $model = CustomerPackagePurchaseModel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Package purchases';

    protected static ?string $modelLabel = 'Package purchase';

    protected static ?string $pluralModelLabel = 'Package purchases';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Purchase details')
                    ->description('Sessions are deducted when the customer reserves a session with isDropIn: false. Cancel a booking to refund sessions.')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'first_name', fn ($q) => $q->orderBy('first_name'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}"))
                            ->searchable(['first_name', 'last_name'])
                            ->required(),
                        Forms\Components\Select::make('package_id')
                            ->relationship('package', 'title')
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('total_sessions')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('remaining_sessions')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Deducted when customer books a session from package; refunded on cancel.'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'expired' => 'Expired',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn (CustomerPackagePurchaseModel $record) => $record->customer
                        ? trim("{$record->customer->first_name} {$record->customer->last_name}")
                        : '-')
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('customer', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"));
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.title')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Total')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_sessions')
                    ->label('Remaining')
                    ->sortable()
                    ->color(fn (int $state) => $state <= 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'warning' => 'expired',
                    ])
                    ->sortable(),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('package_id')
                    ->relationship('package', 'title')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ManageCustomerPackagePurchases::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return true;
    }
}
