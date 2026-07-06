<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use App\Filament\Concerns\ScopesToUserBranch;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Support\BranchContext;
use App\Support\BranchSettings;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Filament\Actions;
use Carbon\Carbon;

class AppointmentsRelationManager extends RelationManager
{
    use ScopesToUserBranch;

    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Appointments';

    protected static \BackedEnum|string|null $icon = Heroicon::OutlinedCalendarDays;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Appointment')
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship(
                                'service',
                                'name',
                                fn ($query) => BranchContext::scopeServicesForBranch($query),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Hidden::make('provider_id')
                            ->default(fn () => $this->getOwnerRecord()->id),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->relationship(
                                'branch',
                                'name',
                                fn ($query) => $query
                                    ->when(
                                        ($branchId = BranchContext::scopedBranchId()),
                                        fn ($scopedQuery) => $scopedQuery->whereKey($branchId),
                                        fn ($scopedQuery) => $scopedQuery
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->orderBy('name'),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->default(fn () => BranchContext::scopedBranchId() ?? BranchSettings::defaultBranchId())
                            ->required()
                            ->visible(fn (): bool => ! BranchContext::isScoped()),
                        Forms\Components\DateTimePicker::make('booking_start')
                            ->label('Starts')
                            ->required(),
                        Forms\Components\DateTimePicker::make('booking_end')
                            ->label('Ends')
                            ->required()
                            ->after('booking_start'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'approved' => 'Approved',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                                'canceled' => 'Canceled',
                            ])
                            ->default('approved')
                            ->required(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal notes')
                            ->rows(2),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => static::applyBranchScope($query))
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Default')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_start')
                    ->label('Starts')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_end')
                    ->label('Ends')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('week')
                    ->label('Week')
                    ->form([
                        Forms\Components\DatePicker::make('week_start')
                            ->label('Week of')
                            ->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['week_start'])) {
                            return $query;
                        }

                        $start = Carbon::parse($data['week_start'])->startOfWeek();
                        $end = (clone $start)->endOfWeek();

                        return $query
                            ->whereDate('booking_start', '>=', $start->toDateString())
                            ->whereDate('booking_start', '<=', $end->toDateString());
                    }),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add appointment')
                    ->mutateFormDataUsing(function (array $data): array {
                        if ($branchId = BranchContext::scopedBranchId()) {
                            $data['branch_id'] = $branchId;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('booking_start', 'desc');
    }
}

