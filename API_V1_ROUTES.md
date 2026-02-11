# API v1 — Mobile app contract

Base URL: **`/api/v1`**  
All app data endpoints require **`Authorization: Bearer <token>`** (except auth send-code and verify).

---

## Public (no auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/send-code` | Send SMS code. Body: `{ "phone": string [, "firstName", "lastName", "email" ] }` |
| POST | `/api/v1/auth/verify` | Verify code and get token. Body: `{ "phone": string, "code": string }` → returns `token`, `customer` |

---

## Authenticated (Bearer token required)

Send header: **`Authorization: Bearer <token>`**

### Home screen

| Method | Endpoint | Response shape |
|--------|----------|----------------|
| GET | `/api/v1/banners` | `[{ "image": string, "URL": string }, ...]` |
| GET | `/api/v1/instructors` | `[{ "id": string, "name": string, "image": string, "position": string, "brief": string }, ...]` |
| GET | `/api/v1/service` | `[{ "id": string, "name": string, "level": string, "image": string, "brief": string }, ...]` |

### Schedule screen

| Method | Endpoint | Response shape |
|--------|----------|----------------|
| GET | `/api/v1/instructors/simple` | `[{ "id": string, "name": string }, ...]` |
| GET | `/api/v1/service/simple` | `[{ "id": string, "name": string }, ...]` |
| GET | `/api/v1/sessions` | Query: `date`, `serviceID`, `instructorID` (all optional). Response: `[{ "id": string, "instructor": string, "service": string, "date": string (ISO8601), "isBooked": bool, "isFull": bool, "canCancel": bool, "canBook": bool, "minutesBeforeCancellation": number }, ...]` |
| POST | `/api/v1/session-bookings` | Book one session. Body: `{ "sessionID": number [, "persons": number ] }` |
| POST | `/api/v1/cancel-booking` | Cancel one booking. Body: `{ "sessionID": number [, "customerBookingId": number ] }` |

### Packages screen

| Method | Endpoint | Response shape |
|--------|----------|----------------|
| GET | `/api/v1/package-offers` | `[{ "id": int, "name": string, "price": number, "sessions": int, "description": string, "expirationMonths": int }, ...]` |
| POST | `/api/v1/purchase-package` | Purchase one package. Body: `{ "packageId": number }` |

### Profile & logout

| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/auth/me` — current customer |
| PUT | `/api/v1/auth/me` — update profile (firstName, lastName, email) |
| POST | `/api/v1/auth/logout` — revoke token |

---

## Auth flow

1. **Send code:** `POST /api/v1/auth/send-code` with `{ "phone": "+201234567890" }`.
2. **Verify & login:** `POST /api/v1/auth/verify` with `{ "phone": "...", "code": "123456" }` → returns `token` and `customer`.
3. Use `Authorization: Bearer <token>` on all other requests.
