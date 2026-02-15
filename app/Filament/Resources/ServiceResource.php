<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Service')
                    ->tabs([
                        Tab::make('General')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Components\Section::make('Details')
                                    ->description('Basic name and description shown to customers.')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g. Morning Yoga'),
                                        Forms\Components\Textarea::make('description')
                                            ->rows(4)
                                            ->placeholder('Describe the service for the app and website.'),
                                    ])->columns(2),
                                Components\Section::make('Visibility')
                                    ->description('Category and visibility in the app.')
                                    ->icon(Heroicon::OutlinedEye)
                                    ->schema([
                                        Forms\Components\Select::make('category_id')
                                            ->label('Category')
                                            ->options([
                                                'Yoga' => 'Yoga',
                                                'Reformer Pilates' => 'Reformer Pilates',
                                            ])
                                            ->searchable()
                                            ->helperText('Product category for app (Yoga or Reformer Pilates)'),
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'visible' => 'Visible',
                                                'hidden' => 'Hidden',
                                            ])
                                            ->searchable()
                                            ->default('visible')
                                            ->required(),
                                    ])->columns(2),
                            ]),
                        Tab::make('Pricing & capacity')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->schema([
                                Components\Section::make('Pricing & capacity')
                                    ->description('Price, duration, and how many people can book.')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->schema([
                                        Forms\Components\TextInput::make('price')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required()
                                            ->default(0),
                                        Forms\Components\TextInput::make('duration')
                                            ->numeric()
                                            ->label('Duration (seconds)')
                                            ->default(1800)
                                            ->required()
                                            ->helperText('e.g. 1800 = 30 minutes'),
                                        Forms\Components\TextInput::make('min_capacity')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required(),
                                        Forms\Components\TextInput::make('max_capacity')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required(),
                                        Forms\Components\TextInput::make('time_before')
                                            ->label('Minutes before cancellation')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->helperText('Cut-off before session start'),
                                        Forms\Components\TextInput::make('time_after')
                                            ->label('Minutes after')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                                        Forms\Components\TextInput::make('position')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->helperText('Sort order in lists'),
                                    ])->columns(3),
                            ]),
                        Tab::make('Images')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->schema([
                                Components\Section::make('Media & display')
                                    ->description('Upload images shown in the app. Main image is used as the primary thumbnail.')
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->schema([
                                        Forms\Components\TextInput::make('color_hex')
                                            ->label('Color (hex)')
                                            ->maxLength(7)
                                            ->placeholder('#000000'),
                                        Forms\Components\FileUpload::make('picture_full_path')
                                            ->label('Main image (full size)')
                                            ->image()
                                            ->directory('services')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->imagePreviewHeight(200)
                                            ->panelLayout('grid'),
                                        Forms\Components\FileUpload::make('picture_thumb_path')
                                            ->label('Thumbnail image')
                                            ->image()
                                            ->directory('services/thumbs')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->imagePreviewHeight(150)
                                            ->panelLayout('grid'),
                                        Forms\Components\FileUpload::make('gallery')
                                            ->label('Gallery images')
                                            ->image()
                                            ->multiple()
                                            ->directory('services/gallery')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->reorderable()
                                            ->imagePreviewHeight(120)
                                            ->panelLayout('grid'),
                                        Forms\Components\Toggle::make('show')
                                            ->label('Show in listing')
                                            ->default(true),
                                    ])->columns(1),
                            ]),
                        Tab::make('Payment & extras')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Components\Section::make('Deposit & payment')
                                    ->description('Deposit and payment options for this service.')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->schema([
                                        Forms\Components\TextInput::make('deposit')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0)
                                            ->default(0),
                                        Forms\Components\Select::make('deposit_payment')
                                            ->options([
                                                'disabled' => 'Disabled',
                                                'enabled' => 'Enabled',
                                            ])
                                            ->default('disabled'),
                                        Forms\Components\Toggle::make('deposit_per_person')
                                            ->label('Deposit per person')
                                            ->default(true),
                                        Forms\Components\Toggle::make('full_payment')
                                            ->label('Full payment')
                                            ->default(false),
                                        Forms\Components\Toggle::make('aggregated_price')
                                            ->label('Aggregated price')
                                            ->default(true),
                                    ])->columns(2),
                                Components\Section::make('Recurring & extras')
                                    ->description('Recurring bookings and extra options.')
                                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                                    ->schema([
                                        Forms\Components\TextInput::make('recurring_cycle')
                                            ->maxLength(255)
                                            ->default('disabled'),
                                        Forms\Components\TextInput::make('recurring_sub')
                                            ->maxLength(255)
                                            ->default('future'),
                                        Forms\Components\TextInput::make('recurring_payment')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                                        Forms\Components\TextInput::make('min_selected_extras')
                                            ->numeric()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('max_extra_people')
                                            ->numeric()
                                            ->minValue(0),
                                        Forms\Components\Toggle::make('mandatory_extra')
                                            ->label('Mandatory extra')
                                            ->default(false),
                                        Forms\Components\Toggle::make('bringing_anyone')
                                            ->label('Bringing anyone')
                                            ->default(true),
                                    ])->columns(2),
                            ]),
                    ])
                    ->persistTabInQueryString('service_tab')
                    ->activeTab(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => gmdate('H:i', $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_capacity')
                    ->label('Min')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('max_capacity')
                    ->label('Max')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('time_before')
                    ->label('Mins before cancel')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('position')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('color_hex')
                    ->label('Color')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('show')
                    ->boolean()
                    ->label('Show')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category_id')
                    ->label('Category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Yoga' => 'success',
                        'Reformer Pilates' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'visible',
                        'danger' => 'hidden',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options([
                        'Yoga' => 'Yoga',
                        'Reformer Pilates' => 'Reformer Pilates',
                    ]),
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
            'index' => Pages\ManageServices::route('/'),
        ];
    }
}

