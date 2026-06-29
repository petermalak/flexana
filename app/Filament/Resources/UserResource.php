<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnlyResource;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    use SuperAdminOnlyResource;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Admin users';

    protected static ?string $modelLabel = 'Admin user';

    protected static ?string $pluralModelLabel = 'Admin users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Account')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        Forms\Components\Select::make('admin_role')
                            ->label('Role')
                            ->options([
                                BranchContext::ROLE_SUPER_ADMIN => 'Super admin',
                                BranchContext::ROLE_BRANCH_ADMIN => 'Branch admin',
                            ])
                            ->required()
                            ->native(false)
                            ->live(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => Branch::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('admin_role') === BranchContext::ROLE_BRANCH_ADMIN)
                            ->visible(fn (Get $get): bool => $get('admin_role') === BranchContext::ROLE_BRANCH_ADMIN)
                            ->helperText('Branch admins can only see data for this branch.'),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        BranchContext::ROLE_SUPER_ADMIN => 'Super admin',
                        BranchContext::ROLE_BRANCH_ADMIN => 'Branch admin',
                        default => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('All branches')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => ! $record->isSuperAdmin() || User::query()->role(BranchContext::ROLE_SUPER_ADMIN)->count() > 1),
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
            'index' => Pages\ManageUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->role([
                BranchContext::ROLE_SUPER_ADMIN,
                BranchContext::ROLE_BRANCH_ADMIN,
            ]);
    }
}
