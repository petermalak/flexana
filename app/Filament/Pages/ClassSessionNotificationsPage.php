<?php

namespace App\Filament\Pages;

use App\Application\Bookings\AdminSessionNotificationService;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Support\ClassReminderSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClassSessionNotificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'session-notifications';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static \UnitEnum|string|null $navigationGroup = 'Scheduling';

    protected static ?string $navigationLabel = 'Session notifications';

    protected static ?string $title = 'Session notifications';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            ...ClassReminderSettings::formDefaults(),
            'horizon_days' => 14,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tz = (string) config('sessions.schedule_timezone', config('app.business_timezone', config('app.timezone')));

        return $schema
            ->components([
                Section::make('Automatic reminders (one day before)')
                    ->description("Sent daily at the chosen time in {$tz}. Manual sends below are independent and can be used anytime.")
                    ->columns(4)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Automatic reminders enabled')
                            ->default(true),
                        Toggle::make('email_enabled')
                            ->label('Send email')
                            ->default(true),
                        Toggle::make('push_enabled')
                            ->label('Send push notification')
                            ->default(true),
                        TimePicker::make('send_at')
                            ->label('Send at')
                            ->seconds(false)
                            ->required()
                            ->default('09:00'),
                        Select::make('horizon_days')
                            ->label('Upcoming sessions')
                            ->options([
                                7 => 'Next 7 days',
                                14 => 'Next 14 days',
                                30 => 'Next 30 days',
                                60 => 'Next 60 days',
                            ])
                            ->default(14)
                            ->live()
                            ->afterStateUpdated(fn () => $this->flushCachedTableRecords())
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ]),
                EmbeddedTable::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveSettings')
                ->label('Save reminder settings')
                ->icon('heroicon-o-check')
                ->action(function (): void {
                    $this->form->validate();
                    $state = $this->form->getState();

                    ClassReminderSettings::save([
                        'enabled' => (bool) ($state['enabled'] ?? false),
                        'email_enabled' => (bool) ($state['email_enabled'] ?? false),
                        'push_enabled' => (bool) ($state['push_enabled'] ?? false),
                        'send_at' => $this->normalizeSendAt($state['send_at'] ?? '09:00'),
                    ]);

                    Notification::make()
                        ->title('Reminder settings saved')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $horizonDays = (int) ($this->data['horizon_days'] ?? 14);
        $until = now()->addDays(max(1, $horizonDays))->endOfDay();

        return $table
            ->query($this->upcomingSessionsQuery($until))
            ->columns([
                TextColumn::make('booking_start')
                    ->label('Starts')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('service.name')
                    ->label('Class')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider.name')
                    ->label('Instructor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('active_attendees')
                    ->label('Attendees')
                    ->numeric()
                    ->alignCenter()
                    ->formatStateUsing(function ($state, AppointmentModel $record): string {
                        $max = (int) ($record->service?->max_capacity ?? 1);

                        return ((int) $state) . ' / ' . $max;
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('booking_start')
            ->emptyStateHeading('No upcoming sessions')
            ->emptyStateDescription('Sessions with a start time in the future will appear here.')
            ->actions([
                Action::make('notifyPush')
                    ->label('Push')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->requiresConfirmation()
                    ->modalHeading('Send push notifications')
                    ->modalDescription('Sends a class reminder push to all active attendees on this session who have the app installed.')
                    ->action(fn (AppointmentModel $record) => $this->sendForRecord($record, push: true, email: false)),
                Action::make('notifyEmail')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->modalHeading('Send email reminders')
                    ->modalDescription('Sends a class reminder email to all active attendees on this session.')
                    ->action(fn (AppointmentModel $record) => $this->sendForRecord($record, push: false, email: true)),
                Action::make('notifyBoth')
                    ->label('Both')
                    ->icon('heroicon-o-bell-alert')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Send push and email')
                    ->modalDescription('Sends both push and email reminders to all active attendees on this session.')
                    ->action(fn (AppointmentModel $record) => $this->sendForRecord($record, push: true, email: true)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkPush')
                        ->label('Send push')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $this->sendForRecords($records, push: true, email: false)),
                    BulkAction::make('bulkEmail')
                        ->label('Send email')
                        ->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $this->sendForRecords($records, push: false, email: true)),
                    BulkAction::make('bulkBoth')
                        ->label('Send push and email')
                        ->icon('heroicon-o-bell-alert')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $this->sendForRecords($records, push: true, email: true)),
                ]),
            ]);
    }

    protected function upcomingSessionsQuery(Carbon $until): Builder
    {
        return AppointmentModel::query()
            ->with(['service', 'provider'])
            ->withSum(['bookings as active_attendees' => function (Builder $query): void {
                $query->whereNull('cancelled_at')
                    ->whereIn('status', ['confirmed', 'pending']);
            }], 'party_size')
            ->where('booking_start', '>=', now())
            ->where('booking_start', '<=', $until)
            ->orderBy('booking_start');
    }

    protected function sendForRecord(AppointmentModel $record, bool $push, bool $email): void
    {
        $service = app(AdminSessionNotificationService::class);
        $stats = $push && $email
            ? $service->sendPushAndEmailForAppointment($record)
            : ($push ? $service->sendPushForAppointment($record) : $service->sendEmailForAppointment($record));

        $this->notifyStats('Notifications sent', $stats);
    }

    /**
     * @param  iterable<int, AppointmentModel>  $records
     */
    protected function sendForRecords(iterable $records, bool $push, bool $email): void
    {
        $service = app(AdminSessionNotificationService::class);
        $totals = [
            'push_sent' => 0,
            'email_sent' => 0,
            'skipped_no_device' => 0,
            'skipped_no_email' => 0,
            'push_failed' => 0,
            'email_failed' => 0,
            'bookings' => 0,
        ];

        foreach ($records as $record) {
            if (! $record instanceof AppointmentModel) {
                continue;
            }

            $stats = $push && $email
                ? $service->sendPushAndEmailForAppointment($record)
                : ($push ? $service->sendPushForAppointment($record) : $service->sendEmailForAppointment($record));

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + ($stats[$key] ?? 0);
            }
        }

        $this->notifyStats('Bulk notifications sent', $totals);
    }

    /**
     * @param  array{push_sent: int, email_sent: int, skipped_no_device: int, skipped_no_email: int, push_failed: int, email_failed: int, bookings: int}  $stats
     */
    protected function notifyStats(string $title, array $stats): void
    {
        $parts = [
            "{$stats['bookings']} active booking(s)",
            "push sent: {$stats['push_sent']}",
            "email sent: {$stats['email_sent']}",
        ];

        if ($stats['skipped_no_device'] > 0) {
            $parts[] = "push skipped (no device): {$stats['skipped_no_device']}";
        }
        if ($stats['skipped_no_email'] > 0) {
            $parts[] = "email skipped (no address): {$stats['skipped_no_email']}";
        }
        if ($stats['push_failed'] > 0 || $stats['email_failed'] > 0) {
            $parts[] = "failed: push {$stats['push_failed']}, email {$stats['email_failed']}";
        }

        $notification = Notification::make()
            ->title($title)
            ->body(implode(' · ', $parts));

        if ($stats['push_failed'] > 0 || $stats['email_failed'] > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    protected function normalizeSendAt(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $string = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}$/', $string) === 1) {
            return $string;
        }

        return '09:00';
    }
}
