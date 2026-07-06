<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnlyResource;
use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Rules\GoogleMapsUrl;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    use SuperAdminOnlyResource;

    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Branches';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Branch')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->rows(2),
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->directory('branches')
                            ->disk('public')
                            ->visibility('public')
                            ->helperText('Branch photo shown in the mobile app.'),
                        Forms\Components\TextInput::make('map_url')
                            ->label('Google Maps URL')
                            ->maxLength(2048)
                            ->url()
                            ->rules([new GoogleMapsUrl()])
                            ->helperText('Paste a Google Maps link only (e.g. https://maps.google.com/... or https://maps.app.goo.gl/...).'),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default branch')
                            ->helperText('Used when an appointment has no branch selected.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first in lists.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk('public')
                    ->circular(false),
                Tables\Columns\TextColumn::make('address')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('map_url')
                    ->label('Map')
                    ->limit(40)
                    ->url(fn (Branch $record): ?string => $record->map_url)
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
