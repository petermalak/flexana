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
```

- **SMSMISR_SENDER_ID:** **Sender token** from SMS Misr (Sender IDs), not the display name.
- **SMSMISR_ENVIRONMENT:** SMS Misr API uses numeric values: **`1` = Live**, **`2` = Test**.
- **SMSMISR_API_URL:** Bulk SMS endpoint base URL. For the official API docs, this is `https://smsmisr.com/api/SMS/`.

---

## 3. Request format we send

The backend sends a **POST** request (`application/x-www-form-urlencoded`) to:

- **URL:** `SMSMISR_API_URL` (e.g. `https://smsmisr.com/api/SMS/`)
- **Body fields:** `environment`, `username`, `password`, `sender`, `mobile`, `language`, `message`

If the official API uses different parameter names or endpoint path, we can adjust the code in `App\Application\Auth\SmsVerificationService::sendViaSmsMisr()` and/or add more config keys.

---

## 4. Troubleshooting

- **No SMS received:** Check `storage/logs/laravel.log` for `SMS Misr send failed` or `SMS Misr API error`. Fix credentials, sender ID, or URL.
- **API code `1903`:** Invalid username/password (check `SMSMISR_USERNAME` / `SMSMISR_PASSWORD`).
- **API code `1904`:** Invalid sender (sender token is wrong / not activated for the account).
- **API code `1912`:** Invalid environment (must be `1` or `2`).
- **Wrong endpoint/parameters:** Compare with [SMS Misr API](https://smsmisr.com/API) and update `config/sms.php` and `SmsVerificationService::sendViaSmsMisr()` if needed.
- **Test without sending:** Use `SMS_DRIVER=log` to only log the OTP (see `SMS verification code` in the log).
