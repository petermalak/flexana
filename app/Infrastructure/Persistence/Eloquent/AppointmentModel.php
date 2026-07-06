<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Support\BranchSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AppointmentModel extends Model
{
    use HasFactory;

    /** Always use MySQL (Laravel default). Used by admin /admin/amelia-appointments. */
    protected $connection = 'mysql';

    protected $table = 'appointments';

    protected $fillable = [
        'uuid',
        'recurrence_group_id',
        'amelia_appointment_id',
        'service_id',
        'provider_id',
        'package_id',
        'location_id',
        'branch_id',
        'booking_start',
        'booking_end',
        'status',
        'internal_notes',
    ];

    protected $casts = [
        'booking_start' => 'datetime',
        'booking_end' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            if (empty($appointment->uuid)) {
                $appointment->uuid = Str::uuid()->toString();
            }

            $branchId = $appointment->branch_id;
            if ($branchId === null || $branchId === '' || (int) $branchId === 0) {
                $appointment->branch_id = BranchSettings::defaultBranchId();
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceModel::class, 'service_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'provider_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PackageModel::class, 'package_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationModel::class, 'location_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BranchModel::class, 'branch_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(BookingModel::class, 'appointment_id');
    }

    public function promoCodes(): BelongsToMany
    {
        return $this->belongsToMany(
            PromoCodeModel::class,
            'appointment_promo_code',
            'appointment_id',
            'promo_code_id',
        )->withTimestamps();
    }

    public function adminSelectLabel(): string
    {
        $serviceName = $this->relationLoaded('service')
            ? ($this->service?->name ?? 'Service')
            : ($this->service()->value('name') ?? 'Service');
        $providerName = $this->relationLoaded('provider')
            ? ($this->provider?->name ?? 'Instructor')
            : ($this->provider()->value('name') ?? 'Instructor');
        $starts = $this->booking_start?->format('M j, Y H:i') ?? '—';

        return "{$starts} — {$serviceName} ({$providerName})";
    }

    /**
     * All appointments in the same recurring group (same data, different dates), including this one.
     */
    public function recurrenceGroup(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_group_id', 'recurrence_group_id');
    }

    /**
     * Other appointments in the same recurring group (excluding this one).
     */
    public function recurrenceSiblings(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_group_id', 'recurrence_group_id')
            ->where('id', '!=', $this->id);
    }
}
