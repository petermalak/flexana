<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerPackagePurchaseResource\Pages;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

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
                            ->relationship('customer', 'first_name', fn ($q) => $q ? $q->orderBy('first_name') : $q)
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}"))
                            ->searchable(['first_name', 'last_name', 'phone'])
                            ->searchPrompt('Search by name or phone')
                            ->required(),
                        Forms\Components\Select::make('package_id')
                            ->relationship(
                                'package',
                                'title',
                                fn ($query) => $query ? $query->orderBy('title') : $query,
                            )
                            ->getOptionLabelFromRecordUsing(fn (PackageModel $record): string => self::formatPackageLabel($record))
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
                            ->helperText('Deducted when customer books a session from package; refunded on cancel.')
                            ->disabled(fn ($get): bool => (string) $get('status') !== 'active')
                            ->dehydrated(),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'expired' => 'Expired',
                            ])
                            ->default('active')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set): void {
                                if ((string) $state !== 'active') {
                                    $set('remaining_sessions', 0);
                                }
                            }),
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
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.title')
                    ->label('Package')
                    ->formatStateUsing(fn (CustomerPackagePurchaseModel $record): string => self::formatPackageLabel($record->package))
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('package', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('service_type', 'like', "%{$search}%");
                        });
                    })
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
                    ->label('Package')
                    ->options(fn (): array => PackageModel::query()->orderBy('title')->get()->mapWithKeys(
                        fn (PackageModel $p): array => [(string) $p->id => self::formatPackageLabel($p)],
                    )->all())
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

    private static function formatPackageLabel(?PackageModel $package): string
    {
        if (! $package) {
            return '-';
        }
        $title = $package->title ?? '';
        $type = trim((string) ($package->service_type ?? ''));
        if ($type !== '') {
            return $title.' || '.$type;
        }

        return $title !== '' ? $title : '-';
    }
}
