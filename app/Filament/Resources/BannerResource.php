<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnlyResource;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Support\Icons\Heroicon;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    use SuperAdminOnlyResource;

    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Banners';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Banner')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->directory('banners')
                            ->disk('public')
                            ->visibility('public')
                            ->required()
                            ->helperText('Upload the banner image; it will be stored on the server and served from storage.'),
                        Forms\Components\TextInput::make('link_url')
                            ->label('Link URL')
                            ->maxLength(2048)
                            ->helperText('Optional URL to open when the banner is tapped.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                Banner::CATEGORY_HOME => 'Home carousel',
                                Banner::CATEGORY_POPUP => 'Home popup',
                            ])
                            ->default(Banner::CATEGORY_HOME)
                            ->required(),
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
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk('public')
                    ->circular(false),
                Tables\Columns\TextColumn::make('link_url')
                    ->label('Link')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Banner::CATEGORY_POPUP => 'Home popup',
                        default => 'Home carousel',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        Banner::CATEGORY_HOME => 'Home carousel',
                        Banner::CATEGORY_POPUP => 'Home popup',
                    ]),
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
            'index' => Pages\ManageBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}

