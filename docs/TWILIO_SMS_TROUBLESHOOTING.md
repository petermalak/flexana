# Twilio SMS – not receiving a real SMS on your phone

Use this checklist when verification codes are not arriving on the user's phone.

---

## 1. Use SMS, not WhatsApp

For **real SMS** (text message to the phone number), Twilio must be in **SMS** mode:

```env
SMS_DRIVER=twilio
TWILIO_CHANNEL=sms
TWILIO_FROM=+14472515930
```

If you have **TWILIO_CHANNEL=whatsapp**, the app sends via **WhatsApp** (template message), not SMS. The user would get a WhatsApp message, not an SMS. Set **TWILIO_CHANNEL=sms** (or remove it, default is `sms`) for normal SMS.

---

## 2. Driver and credentials

In `.env`:

```env
SMS_DRIVER=twilio
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+1xxxxxxxxxx
```

- If **SMS_DRIVER=log**, no SMS is sent; the code is only written to `storage/logs/laravel.log`. Set **SMS_DRIVER=twilio** for real sends.
- **TWILIO_FROM** must be a **Twilio phone number** you own (Phone Numbers → Manage → Active numbers). It must support **SMS** and be allowed to send to the destination country (e.g. Egypt +20).

---

## 3. Trial account: verify the destination number

On a **Twilio trial** account you can only send SMS to **verified** numbers:

1. Twilio Console → **Phone Numbers** → **Manage** → **Verified Caller IDs**.
2. Add the phone number that should receive the code (e.g. +201274235122).
3. Complete the verification (Twilio will send a code to that number once).

If the “To” number is not verified, Twilio returns an error (e.g. 21608) and no SMS is delivered. Either verify that number or upgrade to a paid account so you can send to any number.

---

## 4. Check Laravel logs when it fails

When Twilio rejects the request, the backend logs it. After a signup attempt, check:

**File:** `storage/logs/laravel.log`

Search for:

- **Twilio SMS failed** – SMS API error (status + body).
- **Twilio WhatsApp OTP failed** – WhatsApp send error.

Example:

```text
Twilio SMS failed {"to":"+201274235122","status":400,"body":{"code":21608,"message":"The number +201274235122 is unverified. Trial accounts cannot send..."}}
```

Use the **code** and **message** from the log to fix the issue (e.g. 21608 → verify the number; 21659 → invalid “From” number; 21266 → “To” and “From” cannot be the same).

---

## 5. Phone number format

The backend normalizes the number to E.164 (e.g. `+201274235122`). Make sure the app sends the phone in a format we can normalize (e.g. `+201274235122` or `01274235122` for Egypt). Wrong or unsupported formats can cause Twilio to reject the request.

---

## 6. Quick checklist

| Check | What to do |
|--------|------------|
| Real SMS vs WhatsApp | Use `TWILIO_CHANNEL=sms` and a normal Twilio number in `TWILIO_FROM`. |
| Driver | Set `SMS_DRIVER=twilio`. |
| From number | `TWILIO_FROM` = a Twilio number that supports SMS to your country. |
| Trial account | Add the destination number in Verified Caller IDs, or upgrade. |
| Logs | Check `laravel.log` for “Twilio SMS failed” and the error body. |

If the backend returns **“Verification code could not be sent. Please check your number and try again.”**, the SMS provider (Twilio) rejected the request. Check the log line above for the exact Twilio error.
