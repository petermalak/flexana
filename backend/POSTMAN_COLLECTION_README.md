# Flexana API Postman Collections

There are two Postman collections:

1. **Flexana API Collection** – Admin/frontend API (API Key + HMAC signature).
2. **Flexana Mobile API (v1)** – Mobile app API (Bearer token from Login or Signup → Verify).

---

## Flexana Mobile API (v1) — Mobile app

**File:** `Flexana_Mobile_API.postman_collection.json`

- **Base path:** `/api/v1`
- **Auth:** All data endpoints require `Authorization: Bearer <token>`. Get the token from **Login** or **Signup → Verify** (or **Verify with Firebase Code**).
- **Variables:** `base_url` (e.g. `http://127.0.0.1:8000`), `bearer_token` (set by Login / Verify / Verify with Firebase Code test scripts).

**Auth requests:**

- **Login** – POST `{ "phone", "password" }` → token + customer.
- **Signup (backend OTP)** – POST `{ "phone", "firstName?", "lastName?", "email?" }` (no Firebase token). Backend sends OTP. Then **Verify** with `phone` + `code`.
- **Signup (Firebase SMS – mobile)** – POST `phone` + one of `playIntegrityToken` (Android), `safetyNetToken` (Android), `iosReceipt`+`iosSecret` (iOS), or `recaptchaToken` (web). Returns `sessionInfo`. Then **Verify with Firebase Code** with `sessionInfo`, `code`, `phone`.
- **Send Firebase Verification Code** – Same as above (alternative entry point). Body: `phoneNumber` + one of the mobile/web tokens.
- **Verify** – POST `{ "phone", "code", "password?", "password_confirmation?" }` (for backend OTP flow).
- **Verify with Firebase Code** – POST `{ "sessionInfo", "code", "phone", ... }` (after Firebase SMS; saves token).
- **Verify Firebase (idToken)** – POST `{ "idToken", "phone?", ... }` (if app uses Firebase SDK to verify and gets idToken).
- **Forgot Password** – POST `{ "email" }`. **Reset Password** – POST `{ "email", "token", "password", "password_confirmation" }`.
- **Get Me** – GET profile. **Update Me** – PUT profile. **Update Me (with phone change)** – PUT `phone` + `phoneChangeCode` after **Send Phone Change Code**.
- **Send Phone Change Code** – POST `{ "newPhone" }`. **Change Password** – POST `{ "currentPassword", "password", "password_confirmation" }`. **Delete Account** – DELETE.

**Folders:** Auth (all above), Home Screen, Schedule Screen (sessions, book, cancel, **appointments/history**), Packages Screen.

---

## Flexana API Collection — Admin / frontend

**File:** `Flexana_API_Collection.postman_collection.json`

This collection provides the admin/frontend API, formatted to be compatible with the Emilia frontend format.

## 📦 Import Instructions

1. Open Postman
2. Click **Import** button (top left)
3. Select `Flexana_API_Collection.postman_collection.json` and/or `Flexana_Mobile_API.postman_collection.json`
4. The collection(s) will be imported with all endpoints organized by category

## 🔧 Setup Environment Variables

The collection uses the following environment variables:

### Required Variables

1. **`base_url`** - Your API base URL
   - Development: `http://localhost:8000`
   - Production: `https://your-domain.com`

2. **`firebase_token`** - Firebase authentication token
   - Get this from your Firebase authentication system
   - Format: `Bearer {token}` (the Bearer prefix is already included in headers)

### Setting Variables in Postman

1. Click on the collection name
2. Go to the **Variables** tab
3. Set the values:
   - `base_url`: `http://localhost:8000` (or your production URL)
   - `firebase_token`: Your actual Firebase token

Alternatively, you can create a Postman Environment:
1. Click the gear icon (⚙️) in the top right
2. Click **Add** to create a new environment
3. Add variables:
   - `base_url`
   - `firebase_token`
4. Select the environment from the dropdown

## 📋 API Endpoints Overview

### Bookings
- **POST** `/api/v1/bookings` - Create appointment booking (Emilia format)
- **POST** `/api/v1/bookings` - Create package booking (Emilia format)
- **GET** `/api/v1/bookings` - List all bookings

### Services
- **POST** `/api/v1/services` - Create service (Emilia format)
- **GET** `/api/v1/services` - List all services
- **GET** `/api/v1/services/{uuid}` - Get service by UUID
- **PUT** `/api/v1/services/{uuid}` - Update service
- **DELETE** `/api/v1/services/{uuid}` - Delete service

### Customers
- **POST** `/api/v1/users/customers` - Create customer (Emilia/Flutter format)
- **GET** `/api/v1/customers` - List all customers
- **PUT** `/api/v1/customers/{uuid}` - Update customer

### Events
- **GET** `/api/v1/events` - List all events
- **POST** `/api/v1/events` - Create event
- **GET** `/api/v1/events/{uuid}` - Get event by UUID
- **PUT** `/api/v1/events/{uuid}` - Update event
- **DELETE** `/api/v1/events/{uuid}` - Delete event

### Event Instances
- **GET** `/api/v1/event-instances` - List all event instances
- **POST** `/api/v1/event-instances` - Create event instance
- **GET** `/api/v1/event-instances/{uuid}` - Get event instance by UUID
- **PUT** `/api/v1/event-instances/{uuid}` - Update event instance
- **DELETE** `/api/v1/event-instances/{uuid}` - Delete event instance

### Packages
- **GET** `/api/v1/packages` - List all packages
- **POST** `/api/v1/packages` - Create package
- **GET** `/api/v1/packages/{uuid}` - Get package by UUID
- **PUT** `/api/v1/packages/{uuid}` - Update package
- **DELETE** `/api/v1/packages/{uuid}` - Delete package

### Staff/Instructors
- **GET** `/api/v1/staff` - List all staff
- **POST** `/api/v1/staff` - Create staff member
- **GET** `/api/v1/staff/{uuid}` - Get staff by UUID
- **PUT** `/api/v1/staff/{uuid}` - Update staff
- **DELETE** `/api/v1/staff/{uuid}` - Delete staff

### Class Types
- **GET** `/api/v1/class-types` - List all class types
- **POST** `/api/v1/class-types` - Create class type
- **GET** `/api/v1/class-types/{uuid}` - Get class type by UUID
- **PUT** `/api/v1/class-types/{uuid}` - Update class type
- **DELETE** `/api/v1/class-types/{uuid}` - Delete class type

## 🔑 Authentication

All endpoints require Firebase authentication via Bearer token in the Authorization header:

```
Authorization: Bearer {firebase_token}
```

The token is automatically included in all requests via the collection variable.

## 📝 Important Notes

### Booking Endpoints

1. **Appointment Booking**: The `serviceId` in the request body should be the **Event ID** (not UUID) in Flexana system
2. **Package Booking**: Each item in the `package` array creates a separate booking
3. **Customer Creation**: If `customerId` is null, a new customer will be created from the `customer` object

### Service Endpoints

- In Flexana, creating a service through the Emilia format creates an **Event** with service details
- The `providers` array maps to staff/instructors in Flexana
- The `categoryId` can be used to link to class types

### UUID vs ID

- Most endpoints use **UUID** in the URL path (e.g., `/api/v1/events/{uuid}`)
- However, in booking requests, `serviceId` should be the **numeric ID** (not UUID)
- Check the response format to see which identifier is returned

## 🧪 Testing

1. Start with creating a **Class Type** (e.g., "Yoga")
2. Create a **Staff/Instructor**
3. Create a **Service** (which creates an Event)
4. Create an **Event Instance** for scheduling
5. Create a **Customer**
6. Create a **Booking** using the Event ID

## 📤 Sharing with Frontend Team

1. Export the collection:
   - Right-click on the collection
   - Select **Export**
   - Choose **Collection v2.1**
   - Save the file

2. Share the exported file along with:
   - This README
   - Your API base URL
   - Instructions for obtaining Firebase tokens

3. Frontend team should:
   - Import the collection
   - Set up environment variables
   - Update `base_url` and `firebase_token`
   - Test endpoints

## 🔄 Emilia Compatibility

This collection maintains compatibility with the Emilia frontend format:

- ✅ Booking format matches Emilia's `POST /bookings` structure
- ✅ Service format matches Emilia's `POST /services` structure
- ✅ Customer format matches Emilia's `POST /users/customers` structure
- ✅ Response formats are compatible with Flutter/Emilia expectations

## 🐛 Troubleshooting

### 401 Unauthorized
- Check that `firebase_token` is set correctly
- Verify the token is valid and not expired
- Ensure the Authorization header format is correct

### 404 Not Found
- Verify `base_url` is correct
- Check that the endpoint path matches your API routes
- Ensure UUIDs are valid (not numeric IDs where UUIDs are expected)

### 422 Validation Error
- Check the request body format
- Verify required fields are present
- Check field types (strings, numbers, dates, etc.)

### 500 Server Error
- Check Laravel logs: `storage/logs/laravel.log`
- Verify database connection
- Check that all migrations have been run

## 📚 Additional Resources

- Laravel API Documentation
- Filament Admin Panel: `/admin`
- API Routes: Check `routes/api.php`

