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
SMSMISR_SENDER_ID=YourSenderName
SMSMISR_ENVIRONMENT=Production
SMSMISR_API_URL=https://smsmisr.com/api/v2
```

- **SMSMISR_SENDER_ID:** The exact sender name you activated in SMS Misr (e.g. your brand name).
- **SMSMISR_ENVIRONMENT:** `Production` for real SMS; `Test` if their API provides a test mode.
- **SMSMISR_API_URL:** Base URL only (no path). The app sends to `{SMSMISR_API_URL}/SendSMS`. If your API docs use a different path, set `SMSMISR_API_URL` to the full base that matches (e.g. `https://smsmisr.com/api/v2` and we append `/SendSMS`).

---

## 3. Request format we send

The backend sends a **POST** request (JSON) like:

- **URL:** `{SMSMISR_API_URL}/SendSMS`
- **Body:**
  - `Username`, `Password`, `Sender`, `Message`, `Language`, `Environment`, `Mobile` (array of numbers without `+`).

If the official API uses different parameter names or endpoint path, we can adjust the code in `App\Application\Auth\SmsVerificationService::sendViaSmsMisr()` and/or add more config keys.

---

## 4. Troubleshooting

- **No SMS received:** Check `storage/logs/laravel.log` for `SMS Misr send failed` or `SMS Misr API error`. Fix credentials, sender ID, or URL.
- **Wrong endpoint/parameters:** Compare with [SMS Misr API](https://smsmisr.com/API) and update `config/sms.php` and `SmsVerificationService::sendViaSmsMisr()` if needed.
- **Test without sending:** Use `SMS_DRIVER=log` to only log the OTP (see `SMS verification code` in the log).
