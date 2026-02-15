<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
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

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Event')
                    ->tabs([
                        Tab::make('Details & logistics')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Components\Section::make('Details')
                                    ->description('Name, slug, and description for this event or session.')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g. Morning Flow Yoga'),
                                        Forms\Components\TextInput::make('slug')
                                            ->maxLength(255)
                                            ->disabled(fn (?Event $record) => filled($record?->slug))
                                            ->helperText('Auto-generated from name; edit only if needed.'),
                                        Forms\Components\Textarea::make('description')
                                            ->rows(4)
                                            ->placeholder('Describe the session for the app.'),
                                    ])->columns(2),
                                Components\Section::make('Logistics')
                                    ->description('Status, category, timezone, and waitlist.')
                                    ->icon(Heroicon::OutlinedCog6Tooth)
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'scheduled' => 'Scheduled',
                                                'published' => 'Published',
                                                'archived' => 'Archived',
                                            ])
                                            ->searchable()
                                            ->default('draft')
                                            ->required(),
                                        Forms\Components\Select::make('category')
                                            ->label('Service Type (Category)')
                                            ->options([
                                                'Yoga' => 'Yoga',
                                                'Reformer Pilates' => 'Reformer Pilates',
                                            ])
                                            ->searchable()
                                            ->helperText('Select the category for this session'),
                                        Forms\Components\TextInput::make('timezone')
                                            ->default('UTC')
                                            ->maxLength(60)
                                            ->required(),
                                        Forms\Components\Toggle::make('allow_waitlist')
                                            ->label('Allow Waitlist'),
                                        Forms\Components\TextInput::make('minutes_before_cancellation')
                                            ->label('Minutes before cancellation')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('Cut-off minutes before session start when cancellation is no longer allowed'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Pricing & schedule')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->schema([
                                Components\Section::make('Capacity & Pricing')
                                    ->description('Capacity, price, deposit, and publish time.')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->schema([
                                        Forms\Components\TextInput::make('capacity')
                                            ->numeric()
                                            ->minValue(1),
                                        Forms\Components\TextInput::make('price')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required(),
                                        Forms\Components\TextInput::make('deposit_amount')
                                            ->numeric()
                                            ->prefix('$')
                                            ->default(0),
                                        Forms\Components\DateTimePicker::make('published_at')
                                            ->label('Published at'),
                                    ])->columns(3),
                                Components\Section::make('Recurrence & meta')
                                    ->description('Optional recurrence and meta data (JSON).')
                                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                                    ->schema([
                                        Forms\Components\Textarea::make('recurrence')
                                            ->label('Recurrence (JSON)')
                                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? (json_decode($state, true) ?? []) : ($state ?? [])),
                                        Forms\Components\Textarea::make('meta')
                                            ->label('Meta (JSON)')
                                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? (json_decode($state, true) ?? []) : ($state ?? [])),
                                    ])->columns(1)
                                    ->collapsible(),
                            ]),
                        Tab::make('Assignment')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Components\Section::make('Instructor & services')
                                    ->description('Assign instructor, class type, and which services this event uses.')
                                    ->icon(Heroicon::OutlinedUserPlus)
                                    ->schema([
                                        Forms\Components\Select::make('instructor_id')
                                            ->label('Instructor')
                                            ->relationship('instructor', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\Select::make('class_type_id')
                                            ->label('Class Type')
                                            ->relationship('classType', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\CheckboxList::make('services')
                                            ->label('Services')
                                            ->relationship('services', 'name')
                                            ->columns(2),
                                    ])->columns(2),
                            ]),
                    ])
                    ->persistTabInQueryString('event_tab')
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
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                        'info' => 'scheduled',
                        'danger' => 'archived',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label('Instructor')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('classType.name')
                    ->label('Class Type')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\IconColumn::make('allow_waitlist')
                    ->boolean()
                    ->label('Waitlist'),
                Tables\Columns\TextColumn::make('minutes_before_cancellation')
                    ->label('Mins before cancel')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Service Type')
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
            'index' => Pages\ManageEvents::route('/'),
        ];
    }
}

