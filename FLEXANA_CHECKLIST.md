# Flexana Project - Quick Reference Checklist

## ✅ IMPLEMENTED FEATURES

### Database & Models
- [x] Staff/Instructors table and model
- [x] Services table and model
- [x] Class Types table and model
- [x] Events table and model
- [x] Event Instances table and model (with booking dates)
- [x] Packages table and model
- [x] Bookings table and model
- [x] All relationships properly defined

### Events
- [x] Event CRUD operations (Service, Controller, Routes)
- [x] Event data objects (EventData, EventUpsertData)
- [x] Event-instructor relationship
- [x] Event-class type relationship
- [x] Event-service relationship (many-to-many with prices)

### Bookings
- [x] Booking creation (Service, Controller, Routes)
- [x] Booking data objects (BookingCreateData, BookingData)
- [x] Booking listing (pagination)
- [x] Flutter-specific booking endpoint

---

## ❌ MISSING FEATURES

### 1. INSTRUCTORS (Staff) - CRITICAL
- [ ] Application Service (`app/Application/Staff/Services/StaffService.php`)
- [ ] Data Objects (`StaffData.php`, `StaffUpsertData.php`)
- [ ] API Controller (`app/Interfaces/Http/Controllers/Api/StaffController.php`)
- [ ] API Routes (GET, POST, PUT, DELETE)
- [ ] Domain Entity (`app/Domain/Staff/Staff.php`)
- [ ] Repository Interface (`app/Domain/Staff/StaffRepositoryInterface.php`)
- [ ] Repository Implementation (`app/Infrastructure/Persistence/Eloquent/StaffRepository.php`)
- [ ] Filament Resource (`app/Filament/Resources/StaffResource.php`)

### 2. SERVICES - CRITICAL
- [ ] Application Service (`app/Application/Services/Services/ServiceService.php`)
- [ ] Data Objects (`ServiceData.php`, `ServiceUpsertData.php`)
- [ ] Complete API Controller (currently only `store()` exists)
  - [ ] `index()` method
  - [ ] `show()` method
  - [ ] `update()` method
  - [ ] `destroy()` method
- [ ] API Routes (GET, PUT, DELETE)
- [ ] Domain Entity (`app/Domain/Services/Service.php`)
- [ ] Repository Interface (`app/Domain/Services/ServiceRepositoryInterface.php`)
- [ ] Repository Implementation (`app/Infrastructure/Persistence/Eloquent/ServiceRepository.php`)
- [ ] Filament Resource (`app/Filament/Resources/ServiceResource.php`)

### 3. CLASS TYPES - CRITICAL
- [ ] Application Service (`app/Application/ClassTypes/Services/ClassTypeService.php`)
- [ ] Data Objects (`ClassTypeData.php`, `ClassTypeUpsertData.php`)
- [ ] API Controller (`app/Interfaces/Http/Controllers/Api/ClassTypeController.php`)
- [ ] API Routes (GET, POST, PUT, DELETE)
- [ ] Domain Entity (`app/Domain/ClassTypes/ClassType.php`)
- [ ] Repository Interface (`app/Domain/ClassTypes/ClassTypeRepositoryInterface.php`)
- [ ] Repository Implementation (`app/Infrastructure/Persistence/Eloquent/ClassTypeRepository.php`)
- [ ] Filament Resource (`app/Filament/Resources/ClassTypeResource.php`)

### 4. EVENTS - PARTIAL
- [x] Basic CRUD operations
- [ ] Event Instance Service (`app/Application/Events/Services/EventInstanceService.php`)
- [ ] Event Instance Data Objects (`EventInstanceData.php`, `EventInstanceUpsertData.php`)
- [ ] Event Instance API Controller (`app/Interfaces/Http/Controllers/Api/EventInstanceController.php`)
- [ ] Event Instance API Routes (GET, POST, PUT, DELETE)
- [ ] Event Instance Repository methods
- [ ] Service assignment API endpoint
  - [ ] POST `/events/{event}/services` - Attach services
  - [ ] DELETE `/events/{event}/services/{service}` - Detach service
  - [ ] PUT `/events/{event}/services/{service}` - Update service price
- [ ] EventData includes instructor information
- [ ] EventData includes class type information
- [ ] EventData includes services array with prices
- [ ] EventUpsertData includes `instructorUuid` field
- [ ] EventUpsertData includes `classTypeUuid` field
- [ ] EventUpsertData includes `serviceUuids` array field

### 5. PACKAGES - CRITICAL
- [ ] Application Service (`app/Application/Packages/Services/PackageService.php`)
- [ ] Data Objects (`PackageData.php`, `PackageUpsertData.php`)
- [ ] API Controller (`app/Interfaces/Http/Controllers/Api/PackageController.php`)
- [ ] API Routes (GET, POST, PUT, DELETE)
- [ ] Domain Entity (`app/Domain/Packages/Package.php`)
- [ ] Repository Interface (`app/Domain/Packages/PackageRepositoryInterface.php`)
- [ ] Repository Implementation (`app/Infrastructure/Persistence/Eloquent/PackageRepository.php`)
- [ ] Filament Resource (`app/Filament/Resources/PackageResource.php`)
- [ ] Package usage tracking (`used_sessions` field)
- [ ] Package discount application in bookings
- [ ] Package expiry validation
- [ ] Package session count validation

### 6. BOOKINGS - PARTIAL
- [x] Basic creation and listing
- [ ] Update booking method (`BookingService::update()`)
- [ ] Delete/Cancel booking method (`BookingService::destroy()`)
- [ ] Show booking method (`BookingService::show()`)
- [ ] BookingCreateData includes `packageUuid` field
- [ ] BookingCreateData includes `serviceUuid` field
- [ ] Capacity validation (check available spots)
- [ ] Date validation (booking within open/close dates)
- [ ] Event instance lookup/creation in booking creation
- [ ] Service price calculation in booking creation
- [ ] Package discount calculation in booking creation
- [ ] Package validation (not expired, sessions available)
- [ ] Transaction management (DB transactions)
- [ ] EventInstance Domain entity includes `bookingOpenDate`
- [ ] EventInstance Domain entity includes `bookingCloseDate`
- [ ] EventInstance Domain entity includes `instructorUuid`

---

## ⚠️ BUSINESS LOGIC ISSUES

### Validation Missing
- [ ] Event capacity >= instance capacity
- [ ] Booking dates: `booking_open_date < booking_close_date < starts_at`
- [ ] Event dates: `starts_at < ends_at`
- [ ] Package not expired
- [ ] Package sessions available (`used_sessions < total_sessions`)
- [ ] Package class_type matches event class_type
- [ ] Service prices are positive
- [ ] Service is active
- [ ] Instructor is active
- [ ] Party size doesn't exceed capacity
- [ ] Calculated amount matches provided amount

### Calculation Missing
- [ ] Service price calculation (from event_service pivot table)
- [ ] Multiple services price summation
- [ ] Package discount application
- [ ] Final amount = (service prices * party_size) - package_discount

### Error Handling Missing
- [ ] Custom exceptions (CapacityExceededException, BookingDateException, etc.)
- [ ] Transaction rollback on errors
- [ ] Proper error messages for validation failures

---

## 🏗️ ARCHITECTURE ISSUES

### Domain Layer
- [ ] Complete Domain entities for all models
- [ ] Repository interfaces for all entities
- [ ] Repository implementations for all entities
- [ ] Domain logic separated from application logic

### Service Layer
- [ ] All business logic in services (not controllers)
- [ ] Flutter-specific logic moved to service layer
- [ ] Consistent service method signatures
- [ ] Service layer uses repositories (not direct model access)

### Data Layer
- [ ] All data objects include required fields
- [ ] Validation rules comprehensive
- [ ] Data transformation consistent

---

## 📋 API ENDPOINTS CHECKLIST

### Staff/Instructors
- [ ] `GET /api/v1/staff` - List all staff
- [ ] `POST /api/v1/staff` - Create staff
- [ ] `GET /api/v1/staff/{uuid}` - Get staff details
- [ ] `PUT /api/v1/staff/{uuid}` - Update staff
- [ ] `DELETE /api/v1/staff/{uuid}` - Delete staff

### Services
- [ ] `GET /api/v1/services` - List all services
- [x] `POST /api/v1/services` - Create service (EXISTS)
- [ ] `GET /api/v1/services/{uuid}` - Get service details
- [ ] `PUT /api/v1/services/{uuid}` - Update service
- [ ] `DELETE /api/v1/services/{uuid}` - Delete service

### Class Types
- [ ] `GET /api/v1/class-types` - List all class types
- [ ] `POST /api/v1/class-types` - Create class type
- [ ] `GET /api/v1/class-types/{uuid}` - Get class type details
- [ ] `PUT /api/v1/class-types/{uuid}` - Update class type
- [ ] `DELETE /api/v1/class-types/{uuid}` - Delete class type

### Events
- [x] `GET /api/v1/events` - List all events (EXISTS)
- [x] `POST /api/v1/events` - Create event (EXISTS)
- [ ] `GET /api/v1/events/{uuid}` - Get event details
- [x] `PUT /api/v1/events/{uuid}` - Update event (EXISTS)
- [x] `DELETE /api/v1/events/{uuid}` - Delete event (EXISTS)
- [ ] `POST /api/v1/events/{uuid}/services` - Attach services to event
- [ ] `DELETE /api/v1/events/{uuid}/services/{serviceUuid}` - Detach service
- [ ] `PUT /api/v1/events/{uuid}/services/{serviceUuid}` - Update service price

### Event Instances
- [ ] `GET /api/v1/events/{eventUuid}/instances` - List event instances
- [ ] `POST /api/v1/events/{eventUuid}/instances` - Create event instance
- [ ] `GET /api/v1/event-instances/{uuid}` - Get event instance details
- [ ] `PUT /api/v1/event-instances/{uuid}` - Update event instance
- [ ] `DELETE /api/v1/event-instances/{uuid}` - Delete event instance

### Packages
- [ ] `GET /api/v1/packages` - List all packages
- [ ] `POST /api/v1/packages` - Create package
- [ ] `GET /api/v1/packages/{uuid}` - Get package details
- [ ] `PUT /api/v1/packages/{uuid}` - Update package
- [ ] `DELETE /api/v1/packages/{uuid}` - Delete package

### Bookings
- [x] `GET /api/v1/bookings` - List all bookings (EXISTS)
- [x] `POST /api/v1/bookings` - Create booking (EXISTS)
- [ ] `GET /api/v1/bookings/{uuid}` - Get booking details
- [ ] `PUT /api/v1/bookings/{uuid}` - Update booking
- [ ] `DELETE /api/v1/bookings/{uuid}` - Cancel booking

---

## 🧪 TESTING CHECKLIST

### Unit Tests
- [ ] StaffService tests
- [ ] ServiceService tests
- [ ] ClassTypeService tests
- [ ] PackageService tests
- [ ] EventService tests (enhance existing)
- [ ] EventInstanceService tests
- [ ] BookingService tests (enhance existing)
- [ ] Price calculation tests
- [ ] Discount calculation tests
- [ ] Capacity validation tests
- [ ] Date validation tests

### Integration Tests
- [ ] Event creation with services
- [ ] Event instance creation with dates
- [ ] Booking creation with capacity validation
- [ ] Booking creation with date validation
- [ ] Booking creation with package discount
- [ ] Booking creation with service prices
- [ ] Package usage tracking
- [ ] Complete booking flow

### API Tests
- [ ] All CRUD endpoints for each resource
- [ ] Validation error responses
- [ ] Authentication/authorization
- [ ] Error handling
- [ ] Edge cases

---

## 📊 PRIORITY MATRIX

### 🔴 CRITICAL (Do First - Week 1)
1. Add instructor_id and class_type_id to EventUpsertData
2. Add packageUuid and serviceUuid to BookingCreateData
3. Add capacity validation in BookingService
4. Add date validation in BookingService
5. Add event instance lookup in BookingService
6. Add package discount calculation
7. Add service price calculation
8. Complete EventInstance Domain entity

### 🟡 HIGH (Week 2-3)
1. Create EventInstanceService
2. Create PackageService
3. Create StaffService
4. Create ClassTypeService
5. Complete ServiceService CRUD
6. Add transaction management
7. Add custom exceptions

### 🟢 MEDIUM (Month 1-2)
1. Create Filament resources
2. Add comprehensive validation
3. Add unit tests
4. Add integration tests
5. Add API tests

---

## 📝 NOTES

- **Total Missing Features**: ~60+ items
- **Critical Issues**: ~15 items
- **Estimated Time to Complete**: 2-3 weeks for critical items, 1-2 months for full completion
- **Architecture**: Good foundation, needs completion of Domain layer and Service layer
- **Database**: Well-designed, all relationships properly defined
- **Code Quality**: Generally good, but needs validation and error handling improvements

---

**Last Updated**: Based on code review of current codebase
**Reviewer**: AI Code Review Assistant
**Status**: In Progress - Critical items need immediate attention

