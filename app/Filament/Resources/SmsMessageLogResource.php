<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsMessageLogResource\Pages;
use App\Models\SmsMessageLog;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class SmsMessageLogResource extends Resource
{
    protected static ?string $model = SmsMessageLog::class;

    // Hidden tab: not shown in sidebar navigation
    protected static bool $shouldRegisterNavigation = false;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static \UnitEnum|string|null $navigationGroup = 'CRM';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('msisdn')->disabled(),
                Forms\Components\TextInput::make('verification_code')->disabled(),
                Forms\Components\TextInput::make('reason')->disabled(),
                Forms\Components\TextInput::make('driver')->disabled(),
                Forms\Components\Toggle::make('success')->disabled(),
                Forms\Components\TextInput::make('provider_message_id')->disabled(),
                Forms\Components\TextInput::make('provider_code')->disabled(),
                Forms\Components\TextInput::make('provider_cost')->disabled(),
                Forms\Components\TextInput::make('duration_ms')->disabled(),
                Forms\Components\Textarea::make('error_message')->disabled()->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('msisdn')
                    ->label('MSISDN')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('verification_code')
                    ->label('Code')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('success')
                    ->label('Success')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('provider_code')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('provider_cost')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'signup' => 'signup',
                        'password_reset' => 'password_reset',
                        'phone_change' => 'phone_change',
                        'api_otp' => 'api_otp',
                    ]),
                Tables\Filters\TernaryFilter::make('success'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DateTimePicker::make('from'),
                        Forms\Components\DateTimePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
                            ->when($data['until'] ?? null, fn ($q, $until) => $q->where('created_at', '<=', $until));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsMessageLogs::route('/'),
        ];
    }
}

