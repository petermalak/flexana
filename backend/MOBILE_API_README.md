# Flexana Mobile API Documentation

This document describes the mobile API endpoints for the Flexana application.

## Base URL

The base URL for all mobile API endpoints is:
```
http://127.0.0.1:8000/api/mobile
```

For production, update the `base_url` variable in the Postman collection or use your production domain.

## Database Connection

The API connects to the WordPress/Amelia database using the connection defined in `.env`:
- `WP_DB_HOST`
- `WP_DB_PORT`
- `WP_DB_DATABASE`
- `WP_DB_USERNAME`
- `WP_DB_PASSWORD`

## Endpoints

### Home Screen

#### GET /banners
Get banners for home screen.

**Response:**
```json
[
    {
        "image": "string",
        "URL": "string"
    }
]
```

#### GET /instructors
Get instructors with full details for home screen.

**Response:**
```json
[
    {
        "id": "string",
        "name": "string",
        "image": "string",
        "position": "string",
        "brief": "string"
    }
]
```

#### GET /service
Get services with full details for home screen.

**Response:**
```json
[
    {
        "id": "string",
        "name": "string",
        "level": "string",
        "image": "string",
        "brief": "string"
    }
]
```

### Schedule Screen

#### GET /instructors/simple
Get simple list of instructors for schedule screen.

**Response:**
```json
[
    {
        "id": "string",
        "name": "string"
    }
]
```

#### GET /service/simple
Get simple list of services for schedule screen.

**Response:**
```json
[
    {
        "id": "string",
        "name": "string"
    }
]
```

#### GET /sessions
Get sessions based on filters.

**Query Parameters:**
- `date` (optional): Date filter (YYYY-MM-DD format)
- `serviceID` (optional): Filter by service ID
- `instructorID` (optional): Filter by instructor ID

**Response:**
```json
[
    {
        "id": "string",
        "instructor": "string",
        "service": "string",
        "date": "DateTime (ISO 8601)",
        "isBooked": "bool",
        "isFull": "bool",
        "canCancel": "bool",
        "canBook": "bool",
        "minutesBeforeCancellation": "number"
    }
]
```

#### POST /bookings
Book a session.

**Request Body:**
```json
{
    "sessionID": 1,
    "customerId": 1,  // Optional if providing customer object
    "customer": {      // Optional if providing customerId
        "firstName": "string",
        "lastName": "string",
        "email": "string",
        "phone": "string"
    },
    "persons": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Booking created successfully",
    "data": {
        "id": "string",
        "sessionID": "string",
        "customerId": "string"
    }
}
```

#### POST /cancel
Cancel a booking.

**Request Body:**
```json
{
    "sessionID": 1,
    "customerBookingId": 1  // Optional
}
```

**Response:**
```json
{
    "success": true,
    "message": "Booking canceled successfully"
}
```

### Packages Screen

#### GET /packages
Get packages list.

**Response:**
```json
[
    {
        "id": 1,
        "name": "string",
        "price": 99.99,
        "sessions": 10,
        "description": "string",
        "expirationMonths": 1
    }
]
```

#### POST /purchase
Purchase a package.

**Request Body:**
```json
{
    "packageId": 1,
    "customerId": 1,  // Optional if providing customer object
    "customer": {     // Optional if providing customerId
        "firstName": "string",
        "lastName": "string",
        "email": "string",
        "phone": "string"
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Package purchased successfully",
    "data": {
        "id": "string",
        "packageId": "string",
        "customerId": "string"
    }
}
```

## Error Responses

All endpoints return errors in the following format:

```json
{
    "success": false,
    "message": "Error message",
    "errors": {}  // Only present for validation errors
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

## Postman Collection

Import the `Flexana_Mobile_API.postman_collection.json` file into Postman to test all endpoints. The collection includes example requests and responses.

## Notes

1. **Banners**: The banners endpoint currently returns an empty array. You'll need to implement banner management or connect it to your banner data source.

2. **Authentication**: Currently, the mobile API endpoints don't require authentication. You may want to add authentication middleware in the future.

3. **Customer Management**: When booking or purchasing, you can either provide an existing `customerId` or create a new customer by providing the `customer` object.

4. **Cancellation Rules**: The cancellation deadline is calculated based on the service's `timeBefore` setting. Bookings cannot be canceled after the deadline.

5. **Session Capacity**: The `isFull` flag in sessions is calculated based on the service's `maxCapacity` and current bookings.
