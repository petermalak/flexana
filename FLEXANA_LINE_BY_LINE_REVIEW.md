# Flexana Project - Line-by-Line Code Review

This document provides a detailed review of specific code files, identifying issues, missing functionality, and recommendations for each line of critical code.

---

## 1. BOOKING CREATION FLOW

### File: `backend/app/Application/Bookings/Data/BookingCreateData.php`

**Lines 13-36**: Constructor parameters
- ✅ **Line 15**: `eventUuid` - Required, good
- ✅ **Line 17**: `eventInstanceUuid` - Nullable, good (but see issue below)
- ✅ **Line 19**: `customerUuid` - Required, good
- ✅ **Line 21**: `partySize` - Required, good
- ✅ **Lines 23-27**: Amount fields - Good structure
- ❌ **MISSING**: `packageUuid` field - Packages cannot be assigned during booking creation
- ❌ **MISSING**: `serviceUuid` field - Direct service bookings not supported

**Lines 39-54**: Validation rules
- ✅ **Line 42**: Event UUID validation - Good
- ✅ **Line 43**: Event instance UUID nullable - Good
- ✅ **Line 45**: Customer UUID validation - Good
- ✅ **Line 45**: Party size min:1 - Good
- ⚠️ **Line 45**: Missing max validation for party size
- ⚠️ **Line 46**: Missing validation that total_amount matches calculated price
- ⚠️ **Line 48**: Missing validation that balance_amount = total_amount - deposit_amount

**Recommendations**:
```php
// Add missing fields
public ?string $packageUuid,
public ?string $serviceUuid,

// Add validation rules
'partySize' => ['required', 'integer', 'min:1', 'max:100'], // Add max
'packageUuid' => ['nullable', 'uuid'],
'serviceUuid' => ['nullable', 'uuid'],
```

---

### File: `backend/app/Application/Bookings/Services/BookingService.php`

**Lines 28-53**: `create()` method
- ✅ **Line 30**: Finds event by UUID - Good
- ✅ **Line 31**: Finds customer by UUID - Good
- ✅ **Lines 33-34**: Abort if not found - Good error handling
- ❌ **CRITICAL Line 38**: `event_instance_id` is hardcoded to `null`
  - Should find event instance by `$payload->eventInstanceUuid` if provided
  - Should validate event instance belongs to event
- ❌ **CRITICAL Line 36**: Missing capacity validation
  - Should check if `party_size` exceeds available capacity
  - Should count existing bookings for the instance
- ❌ **CRITICAL Line 40**: Missing booking date validation
  - Should check if booking is within `booking_open_date` and `booking_close_date`
  - Should check if booking is before `starts_at`
- ❌ **CRITICAL Line 43**: `total_amount` taken directly from payload
  - Should calculate from event/service prices
  - Should apply package discount if package is provided
  - Should validate calculated amount matches provided amount
- ❌ **MISSING**: Package lookup if `packageUuid` is provided
- ❌ **MISSING**: Service lookup if `serviceUuid` is provided
- ❌ **MISSING**: Transaction wrapper for data consistency
- ❌ **MISSING**: Event instance creation if not provided but dates are given

**Recommended Implementation**:
```php
public function create(BookingCreateData $payload): BookingData
{
    DB::beginTransaction();
    try {
        $event = $this->events->findByUuid($payload->eventUuid);
        $customer = $this->customers->findByUuid($payload->customerUuid);
        
        abort_if(!$event, 404, 'Event not found.');
        abort_if(!$customer, 404, 'Customer not found.');
        
        // Find or create event instance
        $eventInstance = null;
        if ($payload->eventInstanceUuid) {
            $eventInstance = $this->eventInstances->findByUuid($payload->eventInstanceUuid);
            abort_if(!$eventInstance, 404, 'Event instance not found.');
            abort_if($eventInstance->eventUuid !== $event->uuid, 400, 'Event instance does not belong to event.');
            
            // Validate booking dates
            $now = now();
            if ($eventInstance->bookingOpenDate && $now < $eventInstance->bookingOpenDate) {
                abort(400, 'Booking is not yet open.');
            }
            if ($eventInstance->bookingCloseDate && $now > $eventInstance->bookingCloseDate) {
                abort(400, 'Booking is closed.');
            }
            if ($now > $eventInstance->startsAt) {
                abort(400, 'Cannot book past events.');
            }
            
            // Validate capacity
            $existingBookings = $this->bookings->countByEventInstance($eventInstance->id);
            $availableCapacity = $eventInstance->capacity ?? $event->capacity;
            if ($existingBookings + $payload->partySize > $availableCapacity) {
                abort(400, 'Capacity exceeded.');
            }
        }
        
        // Calculate pricing
        $totalAmount = $this->calculateBookingPrice($event, $eventInstance, $payload);
        
        // Validate amounts match
        if (abs($totalAmount - $payload->totalAmount) > 0.01) {
            abort(400, 'Calculated amount does not match provided amount.');
        }
        
        $booking = $this->bookings->create([
            'event_id' => $event->id,
            'event_instance_id' => $eventInstance?->id,
            'customer_id' => $customer->id,
            'package_id' => $payload->packageUuid ? $this->packages->findByUuid($payload->packageUuid)?->id : null,
            'service_id' => $payload->serviceUuid ? $this->services->findByUuid($payload->serviceUuid)?->id : null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'party_size' => $payload->partySize,
            'total_amount' => $totalAmount,
            'deposit_amount' => $payload->depositAmount,
            'balance_amount' => $payload->balanceAmount,
            'currency' => $payload->currency,
            'channel' => $payload->channel,
            'answers' => $payload->answers,
            'notes' => $payload->notes,
        ]);
        
        DB::commit();
        return BookingData::from($this->mapToArray($booking));
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

---

## 2. EVENT CREATION FLOW

### File: `backend/app/Application/Events/Data/EventUpsertData.php`

**Lines 15-40**: Constructor parameters
- ✅ **Line 17**: `name` - Required, good
- ✅ **Line 19**: `slug` - Nullable, good
- ✅ **Line 29**: `capacity` - Nullable, good
- ✅ **Line 31**: `price` - Required, good
- ❌ **MISSING Line ~35**: `instructor_id` or `instructorUuid` field
- ❌ **MISSING Line ~36**: `class_type_id` or `classTypeUuid` field
- ❌ **MISSING Line ~37**: `service_ids` or `serviceUuids` array field

**Lines 43-59**: Validation rules
- ✅ **Line 46**: Name validation - Good
- ✅ **Line 49**: Status enum validation - Good
- ⚠️ **Line 52**: Capacity min:1 but no max
- ❌ **MISSING**: Instructor UUID validation
- ❌ **MISSING**: Class type UUID validation
- ❌ **MISSING**: Service UUIDs array validation

**Recommended Addition**:
```php
public function __construct(
    // ... existing fields ...
    #[StringType]
    public ?string $instructorUuid,
    #[StringType]
    public ?string $classTypeUuid,
    #[ArrayType]
    public ?array $serviceUuids, // Array of service UUIDs with optional prices
) {
}

public static function rules(): array
{
    return [
        // ... existing rules ...
        'instructorUuid' => ['nullable', 'uuid'],
        'classTypeUuid' => ['nullable', 'uuid'],
        'serviceUuids' => ['nullable', 'array'],
        'serviceUuids.*' => ['uuid'],
    ];
}
```

---

### File: `backend/app/Application/Events/Services/EventService.php`

**Lines 24-29**: `create()` method
- ✅ **Line 26**: Creates event from payload - Good
- ❌ **CRITICAL**: Doesn't handle `instructor_id` or `class_type_id`
- ❌ **CRITICAL**: Doesn't attach services to event
- ❌ **MISSING**: Validation that instructor exists
- ❌ **MISSING**: Validation that class type exists
- ❌ **MISSING**: Validation that services exist

**Lines 51-69**: `mapToArray()` method
- ✅ **Lines 54-67**: Maps basic fields - Good
- ❌ **MISSING**: Instructor information (uuid, name)
- ❌ **MISSING**: Class type information (uuid, name)
- ❌ **MISSING**: Services array with prices

**Recommended Implementation**:
```php
public function create(EventUpsertData $payload): EventData
{
    // Validate instructor if provided
    if ($payload->instructorUuid) {
        $instructor = $this->staff->findByUuid($payload->instructorUuid);
        abort_if(!$instructor, 404, 'Instructor not found.');
    }
    
    // Validate class type if provided
    if ($payload->classTypeUuid) {
        $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
        abort_if(!$classType, 404, 'Class type not found.');
    }
    
    $eventData = $payload->toArray();
    $eventData['instructor_id'] = $instructor->id ?? null;
    $eventData['class_type_id'] = $classType->id ?? null;
    
    $event = $this->events->create($eventData);
    
    // Attach services if provided
    if ($payload->serviceUuids) {
        $this->attachServicesToEvent($event, $payload->serviceUuids);
    }
    
    return EventData::from($this->mapToArray($event));
}

private function mapToArray(Event $event): array
{
    $eventModel = EventModel::find($event->id); // Get Eloquent model for relationships
    
    return [
        'uuid' => $event->uuid,
        'slug' => $event->slug,
        'name' => $event->name,
        'category' => $event->category,
        'status' => $event->status->value,
        'timezone' => $event->timezone,
        'description' => $event->description,
        'capacity' => $event->capacity,
        'price' => $event->price,
        'depositAmount' => $event->depositAmount,
        'allowWaitlist' => $event->allowWaitlist,
        'recurrence' => $event->recurrence,
        'meta' => $event->meta,
        'publishedAt' => $event->publishedAt,
        'instructor' => $eventModel->instructor ? [
            'uuid' => $eventModel->instructor->uuid,
            'name' => $eventModel->instructor->name,
        ] : null,
        'classType' => $eventModel->classType ? [
            'uuid' => $eventModel->classType->uuid,
            'name' => $eventModel->classType->name,
        ] : null,
        'services' => $eventModel->services->map(fn($service) => [
            'uuid' => $service->uuid,
            'name' => $service->name,
            'price' => $service->pivot->price ?? $service->price,
        ])->toArray(),
    ];
}
```

---

## 3. EVENT INSTANCE DOMAIN ENTITY

### File: `backend/app/Domain/Events/EventInstance.php`

**Lines 9-20**: Constructor
- ✅ **Line 13**: `startsAt` - Good
- ✅ **Line 14**: `endsAt` - Good
- ✅ **Line 15**: `capacity` - Good
- ❌ **MISSING**: `booking_open_date` field
- ❌ **MISSING**: `booking_close_date` field
- ❌ **MISSING**: `instructor_id` or `instructorUuid` field

**Issue**: The Domain entity doesn't match the database model. The `EventInstanceModel` has these fields, but the Domain entity doesn't.

**Recommended Fix**:
```php
final class EventInstance
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $eventUuid,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?int $capacity,
        public ?string $location,
        public string $status,
        public ?array $resources,
        public ?CarbonImmutable $bookingOpenDate,  // ADD THIS
        public ?CarbonImmutable $bookingCloseDate,  // ADD THIS
        public ?string $instructorUuid,            // ADD THIS
    ) {
    }
}
```

---

## 4. BOOKING CONTROLLER - FLUTTER LOGIC

### File: `backend/app/Interfaces/Http/Controllers/Api/BookingController.php`

**Lines 60-190**: `storeFlutter()` method
- ⚠️ **Line 93-136**: Appointment booking logic
  - ❌ **Line 95**: Uses `EventModel::find()` directly - Should use repository
  - ❌ **Line 103**: Calculates price from `event->price` only
    - Should use service prices if services are selected
    - Should apply package discount if package is used
  - ❌ **Line 114**: `eventInstanceUuid` is hardcoded to `null`
    - Should find or create event instance from `bookingStart` date
  - ❌ **Line 129-133**: Updates `booked_at` after creation
    - Should be set during creation or calculated from bookingStart
- ⚠️ **Line 137-179**: Package booking logic
  - ❌ **Line 148**: Same pricing issue - doesn't apply package discount
  - ❌ **Line 149**: `totalAmount` should be discounted
  - ❌ **MISSING**: Package validation (not expired, sessions available)
  - ❌ **MISSING**: Package session tracking (decrement count)

**Recommendations**:
1. Move Flutter-specific logic to a separate service method
2. Use repositories instead of direct model access
3. Apply package discounts
4. Validate and create event instances
5. Track package usage

---

## 5. PACKAGE MODEL

### File: `backend/app/Infrastructure/Persistence/Eloquent/PackageModel.php`

**Lines 17-27**: Fillable fields
- ✅ **Line 20**: `class_type_id` - Good
- ✅ **Line 21**: `title` - Good
- ✅ **Line 22**: `description` - Good
- ✅ **Line 23**: `total_sessions` - Good
- ✅ **Line 24**: `discount` - Good
- ✅ **Line 25**: `price` - Good
- ✅ **Line 26**: `expiry` - Good
- ❌ **MISSING**: `used_sessions` or `remaining_sessions` field
  - Need to track how many sessions have been used
  - Should be incremented when booking is created with package

**Recommendation**: Add migration to add `used_sessions` field:
```php
Schema::table('packages', function (Blueprint $table) {
    $table->unsignedInteger('used_sessions')->default(0)->after('total_sessions');
});
```

---

## 6. MISSING REPOSITORY METHODS

### File: `backend/app/Domain/Events/EventRepositoryInterface.php`

**Expected but Missing Methods**:
- `findInstancesByEventUuid(string $eventUuid): array`
- `findInstancesByDateRange(CarbonImmutable $start, CarbonImmutable $end): array`
- `findAvailableInstances(string $eventUuid, int $partySize): array`

### File: `backend/app/Domain/Bookings/BookingRepositoryInterface.php`

**Expected but Missing Methods**:
- `countByEventInstance(int $eventInstanceId): int`
- `findByEventInstance(int $eventInstanceId): array`
- `findByPackage(string $packageUuid): array`
- `findByCustomer(string $customerUuid): array`

---

## 7. VALIDATION ISSUES

### Missing Validations Across the Codebase:

1. **Event Capacity Validation**
   - No check that instance capacity <= event capacity
   - No check that total bookings <= capacity

2. **Date Validation**
   - No check that `booking_open_date < booking_close_date < starts_at`
   - No check that `starts_at < ends_at`

3. **Package Validation**
   - No check that package is not expired
   - No check that `used_sessions < total_sessions`
   - No check that package class_type matches event class_type

4. **Service Validation**
   - No check that service prices are positive
   - No check that service is active

5. **Instructor Validation**
   - No check that instructor is active
   - No check that instructor is available at event time

---

## SUMMARY OF CRITICAL CODE ISSUES

### Must Fix Immediately:
1. ✅ Add `instructor_id` and `class_type_id` to EventUpsertData
2. ✅ Add `packageUuid` and `serviceUuid` to BookingCreateData
3. ✅ Add capacity validation in BookingService::create()
4. ✅ Add date validation in BookingService::create()
5. ✅ Add event instance lookup/creation in BookingService::create()
6. ✅ Add package discount calculation in booking creation
7. ✅ Add service price calculation in booking creation
8. ✅ Complete EventInstance Domain entity with missing fields
9. ✅ Add instructor and class type to EventData
10. ✅ Add services to EventData

### Should Fix Soon:
1. Create EventInstanceService
2. Create PackageService
3. Create StaffService
4. Create ClassTypeService
5. Create ServiceService (complete CRUD)
6. Add transaction management
7. Add custom exceptions
8. Move Flutter logic to service layer

---

## TESTING PRIORITIES

### Unit Tests (High Priority):
1. BookingService::create() with all validations
2. EventService::create() with services and instructor
3. Package discount calculation
4. Service price calculation
5. Capacity validation logic
6. Date validation logic

### Integration Tests (High Priority):
1. Complete booking flow with event instance
2. Booking with package discount
3. Booking with multiple services
4. Capacity exceeded scenario
5. Booking outside date range scenario

---

This line-by-line review identifies specific code locations that need attention. Each issue should be addressed systematically to ensure the application functions correctly and handles edge cases properly.

