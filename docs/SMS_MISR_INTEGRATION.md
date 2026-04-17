# SMS Misr integration (Egypt)

Use [SMS Misr](https://smsmisr.com) to send OTP SMS to Egyptian numbers. Get credentials and API details from:

- **Account / Sender:** [https://smsmisr.com/Client/Settings](https://smsmisr.com/Client/Settings)
- **API documentation:** [https://smsmisr.com/API](https://smsmisr.com/API)

---

## 1. Get credentials from SMS Misr

1. Register / log in at [SMS Misr](https://smsmisr.com).
2. In **Client → Settings**, ensure you have:
   - An **activated sender ID** (the name that appears as the SMS sender).
3. In the **Developer / API** section (see [smsmisr.com/API](https://smsmisr.com/API)):
   - Create or copy your **API username** and **password**.
   - Note the **base API URL** and **send endpoint** (e.g. `/SendSMS` or `/Request`). If the docs show a different path or parameter names, you can override the URL (see below).

---

## 2. Configure Laravel

In your `.env`:

```env
SMS_DRIVER=smsmisr

SMSMISR_USERNAME=your_api_username
SMSMISR_PASSWORD=your_api_password
SMSMISR_SENDER_ID=your_sender_token
SMSMISR_ENVIRONMENT=1
SMSMISR_API_URL=https://smsmisr.com/api/SMS/

# Optional (recommended): use SMS Misr OTP API (template-based)
SMSMISR_OTP_API_URL=https://smsmisr.com/api/OTP/
SMSMISR_OTP_TEMPLATE=your_template_token
```

- **SMSMISR_SENDER_ID:** **Sender token** from SMS Misr (Sender IDs), not the display name.
- **SMSMISR_ENVIRONMENT:** SMS Misr API uses numeric values: **`1` = Live**, **`2` = Test**.
- **SMSMISR_API_URL:** Bulk SMS endpoint base URL. For the official API docs, this is `https://smsmisr.com/api/SMS/`.

### OTP API environment variables (SMS Misr)

These control the **template-based OTP API** (`https://smsmisr.com/api/OTP/`). When `SMSMISR_OTP_TEMPLATE` is set, verification SMS is sent via OTP API first; otherwise the app uses Bulk SMS (`SMSMISR_API_URL`).

| Variable | Required | Default / example | Purpose |
| --- | --- | --- | --- |
| `SMSMISR_OTP_TEMPLATE` | Yes, to use OTP API | (empty) | **Template token** from SMS Misr console. If unset, OTP API is skipped and Bulk SMS is used. |
| `SMSMISR_OTP_API_URL` | No | `https://smsmisr.com/api/OTP/` | Base URL for OTP API POST requests. |

**Shared with Bulk SMS** (same values for both APIs):

| Variable | Required | Example | Purpose |
| --- | --- | --- | --- |
| `SMS_DRIVER` | Yes | `smsmisr` | Must be `smsmisr` to send via SMS Misr. |
| `SMSMISR_USERNAME` | Yes | — | API username. |
| `SMSMISR_PASSWORD` | Yes | — | API password. |
| `SMSMISR_SENDER_ID` | Yes | — | Sender **token** (not display name). |
| `SMSMISR_ENVIRONMENT` | Yes | `1` or `2` | `1` = Live, `2` = Test (applies to OTP and Bulk). |

---

## 3. Request format we send

### 3.1 Bulk SMS API (fallback)

When OTP API is not configured, the backend sends a **POST** request (`application/x-www-form-urlencoded`) to:

- **URL:** `SMSMISR_API_URL` (e.g. `https://smsmisr.com/api/SMS/`)
- **Body fields:** `environment`, `username`, `password`, `sender`, `mobile`, `language`, `message`

### 3.2 OTP API (preferred)

If `SMSMISR_OTP_TEMPLATE` is set, the backend prefers the **OTP API** (`https://smsmisr.com/api/OTP/`) and sends:

- **URL:** `SMSMISR_OTP_API_URL` (default `https://smsmisr.com/api/OTP/`)
- **Body fields:** `environment`, `username`, `password`, `sender`, `mobile`, `template`, `otp`

If the official API uses different parameter names or endpoint path, we can adjust the code in `App\Application\Auth\SmsVerificationService::sendViaSmsMisrOtp()` / `sendViaSmsMisr()`.

---

## 4. Troubleshooting

- **`.env` is correct but logs still show old URLs / misconfiguration:** Laravel may be using **`bootstrap/cache/config.php`** from a past `php artisan config:cache`. Cached config ignores updated `.env` values. Run `php artisan config:clear` (or `php artisan optimize:clear`), then restart `php artisan serve` / workers. Only use `config:cache` in production after `.env` is final.
- **No SMS received:** Check `storage/logs/laravel.log` for `SMS Misr send failed` or `SMS Misr API error`. Fix credentials, sender ID, or URL.
- **API code `1903`:** Invalid username/password (check `SMSMISR_USERNAME` / `SMSMISR_PASSWORD`).
- **API code `1904`:** Invalid sender (sender token is wrong / not activated for the account).
- **API code `1912`:** Invalid environment (must be `1` or `2`).
- **HTTP 500 when calling `https://smsmisr.com/api/OTP/`:** You are likely sending Bulk SMS parameters (`message`, `language`) to the OTP endpoint. Keep `SMSMISR_API_URL=https://smsmisr.com/api/SMS/` and configure OTP via `SMSMISR_OTP_API_URL` + `SMSMISR_OTP_TEMPLATE`.
- **Wrong endpoint/parameters:** Compare with [SMS Misr API](https://smsmisr.com/API) and update `config/sms.php` and `SmsVerificationService::sendViaSmsMisr()` if needed.
- **Test without sending:** Use `SMS_DRIVER=log` to only log the OTP (see `SMS verification code` in the log).
