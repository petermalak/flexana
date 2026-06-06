<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Domain\Promo\Enums\PromoApplicableType;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Models\Customer;
use App\Services\Paymob\PaymobClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\ApiDateTime;
use App\Support\InternalNotificationMail;
use App\Support\PromoEmailText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebSessionPaymobController extends Controller
{
    // NOTE: redirect is now handled by /paymob/finish which breaks out of iframes.

    public function finish(Request $request)
    {
        $kind = $request->query('payment') === 'success' ? 'success' : 'failed';
        $bookingId = (string) $request->query('booking_id', '');

        $targetUrl = $this->buildConfiguredUrl($kind, array_filter([
            'payment' => $kind === 'success' ? 'success' : 'failed',
            'booking_id' => $bookingId !== '' ? $bookingId : null,
        ]));

        return response()
            ->view('paymob.finish', [
                'targetUrl' => $targetUrl,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function buildConfiguredUrl(string $kind, array $params = []): string
    {
        $base = $kind === 'success'
            ? (string) config('booking_embed.payment_success_redirect_url')
            : (string) config('booking_embed.payment_failed_redirect_url');

        $base = trim($base);
        if ($base === '') {
            return url('/web-session-bookings');
        }

        $parts = parse_url($base);
        $existingQuery = [];
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $existingQuery);
        }

        $merged = array_merge($existingQuery, $params);
        $query = http_build_query($merged);

        $rebuilt = $base;
        if ($query !== '') {
            $rebuilt = strtok($base, '?');
            $rebuilt .= '?'.$query;
        }

        return $rebuilt;
    }
    /**
     * Start Paymob payment, then redirect user to Paymob iframe.
     *
     * Accepts the same payload as POST /api/v1/web-session-bookings.
     */
    public function init(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'spots' => 'nullable|integer|min:1|max:20',
            'promoCode' => 'nullable|string|max:64',
            'isDropIn' => 'nullable|boolean',
            'customer.firstName' => 'required|string|max:80',
            'customer.lastName' => 'required|string|max:80',
            // Avoid DNS validation for embed checkout (dev/offline/test domains).
            'customer.email' => 'required|email:rfc|max:190',
            'customer.phone' => 'nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $isDropIn = array_key_exists('isDropIn', $data) ? (bool) $data['isDropIn'] : true;
        if (! $isDropIn) {
            return response()->json([
                'success' => false,
                'message' => 'Paymob is only used for drop-in payments.',
            ], 400);
        }

        $sessionID = (int) $data['sessionID'];
        $spots = (int) ($data['spots'] ?? 1);
        $promoCode = $data['promoCode'] ?? null;

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings', 'provider'])
            ->find($sessionID);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        $service = $appointment->service;
        $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
        $currentBookings = $appointment->bookings->whereIn('status', ['confirmed', 'pending'])->sum('party_size');
        if (($currentBookings + $spots) > $maxCapacity) {
            return response()->json([
                'success' => false,
                'message' => 'Session is full',
            ], 400);
        }

        if (Carbon::parse($appointment->booking_start)->lte(Carbon::now())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book past sessions',
            ], 400);
        }

        $servicePrice = $service ? (float) ($service->price ?? 0) : 0;
        $subtotalBeforePromo = $servicePrice * $spots;
        $totalPrice = $subtotalBeforePromo;
        $promoRecord = null;

        if ($promoCode) {
            $promoRecord = PromoCodeModel::findByCode($promoCode);
            if ($promoRecord && $promoRecord->invalidReasonForCustomer(null, PromoApplicableType::DropIns) === null) {
                $totalPrice = $totalPrice * (1 - (float) $promoRecord->percent_discount / 100);
            } else {
                $promoRecord = null;
            }
        }

        $currency = (string) config('paymob.currency', 'EGP');
        $amountCents = (int) round(max(0, $totalPrice) * 100);
        if ($amountCents <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment amount.',
            ], 400);
        }

        $intentId = (string) Str::uuid();
        Cache::put(
            'paymob_intent:'.$intentId,
            [
                'payload' => $data,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'promo_code' => $promoCode,
                'created_at' => now()->toISOString(),
            ],
            now()->addMinutes(90),
        );

        try {
            $client = PaymobClient::fromConfig();
            $authToken = $client->authToken();
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is unreachable. Please try again.',
            ], 502);
        }

        try {
            $order = $client->createOrder($authToken, [
            'delivery_needed' => 'false',
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'items' => [[
                'name' => (string) ($service?->name ?? 'Session'),
                'amount_cents' => $amountCents,
                'description' => 'Web session booking',
                'quantity' => 1,
            ]],
            'merchant_order_id' => $intentId,
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is unreachable. Please try again.',
            ], 502);
        }

        $customer = $data['customer'] ?? [];
        $billing = [
            'apartment' => 'NA',
            'email' => (string) ($customer['email'] ?? ''),
            'floor' => 'NA',
            'first_name' => (string) ($customer['firstName'] ?? ''),
            'street' => 'NA',
            'building' => 'NA',
            'phone_number' => (string) ($customer['phone'] ?? ''),
            'shipping_method' => 'NA',
            'postal_code' => 'NA',
            'city' => 'NA',
            'country' => 'EG',
            'last_name' => (string) ($customer['lastName'] ?? ''),
            'state' => 'NA',
        ];

        try {
            $paymentKey = $client->paymentKey($authToken, [
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $order['id'],
            'billing_data' => $billing,
            'currency' => $currency,
            'integration_id' => (int) config('paymob.integration_id'),
            'lock_order_when_paid' => 'true',
            'redirect_url' => url('/paymob/return'),
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is unreachable. Please try again.',
            ], 502);
        }

        $iframeId = (int) config('paymob.iframe_id');
        $redirectUrl = rtrim((string) config('paymob.base_url'), '/')
            .'/api/acceptance/iframes/'.$iframeId
            .'?payment_token='.urlencode($paymentKey);

        return response()->json([
            'success' => true,
            'data' => [
                'intentId' => $intentId,
                'orderId' => $order['id'],
                'amountCents' => $amountCents,
                'currency' => $currency,
                'redirectUrl' => $redirectUrl,
            ],
        ]);
    }

    /**
     * Called by Paymob redirect after payment attempt.
     * Verifies the transaction server-side and creates the booking with payment_status=paid.
     */
    public function return(Request $request)
    {
        $transactionId = (int) $request->query('id', 0);
        if ($transactionId <= 0) {
            return redirect()->route('paymob.finish', ['payment' => 'failed']);
        }

        Log::info('Paymob return redirect received', [
            'transaction_id' => $transactionId,
            'merchant_order_id' => (string) $request->query('merchant_order_id', ''),
            'order' => (string) $request->query('order', ''),
            'success' => (string) $request->query('success', ''),
            'pending' => (string) $request->query('pending', ''),
            'amount_cents' => (string) $request->query('amount_cents', ''),
        ]);

        try {
            $client = PaymobClient::fromConfig();
            $authToken = $client->authToken();
            $tx = $client->transaction($authToken, $transactionId);
        } catch (\Throwable $e) {
            // Never 500 the user on return; treat as a failed payment and let support investigate logs.
            return redirect()->route('paymob.finish', ['payment' => 'failed']);
        }

        $success = (bool) data_get($tx, 'success', false);
        $pending = (bool) data_get($tx, 'pending', false);
        $isVoid = (bool) data_get($tx, 'is_void', false);
        $isRefund = (bool) data_get($tx, 'is_refunded', false);
        $amountCents = (int) data_get($tx, 'amount_cents', 0);
        $currency = (string) data_get($tx, 'currency', (string) config('paymob.currency', 'EGP'));
        $merchantOrderId = (string) data_get($tx, 'order.merchant_order_id', '');
        $paymobOrderId = (int) data_get($tx, 'order.id', 0);

        Log::info('Paymob transaction fetched', [
            'transaction_id' => $transactionId,
            'success' => $success,
            'pending' => $pending,
            'is_void' => $isVoid,
            'is_refunded' => $isRefund,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'merchant_order_id' => $merchantOrderId,
            'order_id' => $paymobOrderId,
        ]);

        if ($pending && ! $success) {
            // Bank can show a hold while Paymob is still pending.
            // Booking will be finalized via /paymob/callback if/when Paymob confirms success.
            return redirect()->route('paymob.finish', [
                'payment' => 'pending',
                'tx' => (string) $transactionId,
            ]);
        }

        if (! $success || $isVoid || $isRefund || $amountCents <= 0 || $merchantOrderId === '') {
            return redirect()->route('paymob.finish', ['payment' => 'failed']);
        }

        $intent = Cache::get('paymob_intent:'.$merchantOrderId);
        if (! is_array($intent)) {
            return redirect()->route('paymob.finish', ['payment' => 'failed']);
        }

        if ((int) ($intent['amount_cents'] ?? -1) !== $amountCents) {
            return redirect()->route('paymob.finish', ['payment' => 'failed']);
        }

        $payload = (array) ($intent['payload'] ?? []);

        // Idempotency: do not create multiple bookings for the same Paymob transaction/order
        $existingPayment = PaymentModel::query()
            ->where('provider', 'paymob')
            ->where(function ($q) use ($transactionId, $paymobOrderId) {
                $q->where('transaction_id', (string) $transactionId);
                if ($paymobOrderId > 0) {
                    $q->orWhere('provider_reference', (string) $paymobOrderId);
                }
            })
            ->first();

        if ($existingPayment) {
            return redirect()->route('paymob.finish', [
                'payment' => 'success',
                'booking_id' => (string) $existingPayment->booking_id,
            ]);
        }

        try {
            $result = $this->createPaidBookingFromPayload($payload, [
                'transaction_id' => (string) $transactionId,
                'provider_reference' => $paymobOrderId > 0 ? (string) $paymobOrderId : $merchantOrderId,
                'currency' => $currency,
                // IMPORTANT: use the exact paid amount from Paymob/intent for booking totals.
                'amount_cents' => $amountCents,
                'meta' => $tx,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('paymob.finish', [
                'payment' => 'failed',
                'reason' => 'finalize_failed',
            ]);
        }

        Cache::forget('paymob_intent:'.$merchantOrderId);

        return redirect()->route('paymob.finish', [
            'payment' => 'success',
            'booking_id' => (string) $result['booking_id'],
        ]);
    }

    /**
     * Paymob server-to-server callback (TRANSACTION processed).
     * This is more reliable than browser redirects, especially when embedded in iframes / 3DS flows.
     */
    public function callback(Request $request)
    {
        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');
        $obj = (array) ($payload['obj'] ?? []);

        if ($type !== 'TRANSACTION' || $obj === []) {
            return response()->json(['ok' => true]);
        }

        $transactionId = (int) ($obj['id'] ?? 0);
        $success = (bool) ($obj['success'] ?? false);
        $pending = (bool) ($obj['pending'] ?? false);
        $isVoid = (bool) ($obj['is_void'] ?? false);
        $isRefunded = (bool) ($obj['is_refunded'] ?? false);
        $amountCents = (int) ($obj['amount_cents'] ?? 0);
        $currency = (string) ($obj['currency'] ?? (string) config('paymob.currency', 'EGP'));
        $order = (array) ($obj['order'] ?? []);
        $merchantOrderId = (string) ($order['merchant_order_id'] ?? '');
        $paymobOrderId = (int) ($order['id'] ?? 0);

        Log::info('Paymob callback received', [
            'transaction_id' => $transactionId,
            'success' => $success,
            'pending' => $pending,
            'is_void' => $isVoid,
            'is_refunded' => $isRefunded,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'merchant_order_id' => $merchantOrderId,
            'order_id' => $paymobOrderId,
        ]);

        if (! $success || $pending || $isVoid || $isRefunded || $amountCents <= 0 || $merchantOrderId === '') {
            return response()->json(['ok' => true]);
        }

        // Idempotency
        $existingPayment = PaymentModel::query()
            ->where('provider', 'paymob')
            ->where(function ($q) use ($transactionId, $paymobOrderId) {
                $q->where('transaction_id', (string) $transactionId);
                if ($paymobOrderId > 0) {
                    $q->orWhere('provider_reference', (string) $paymobOrderId);
                }
            })
            ->first();

        if ($existingPayment) {
            return response()->json(['ok' => true, 'booking_id' => (int) $existingPayment->booking_id]);
        }

        $intent = Cache::get('paymob_intent:'.$merchantOrderId);
        if (! is_array($intent)) {
            // If the user never returned to us, the intent might be missing; we can't create a booking safely.
            return response()->json(['ok' => true]);
        }

        $bookingPayload = (array) ($intent['payload'] ?? []);
        try {
            $result = $this->createPaidBookingFromPayload($bookingPayload, [
                'transaction_id' => (string) $transactionId,
                'provider_reference' => $paymobOrderId > 0 ? (string) $paymobOrderId : $merchantOrderId,
                'currency' => $currency,
                'amount_cents' => $amountCents,
                'meta' => $obj,
            ]);
            Cache::forget('paymob_intent:'.$merchantOrderId);

            return response()->json(['ok' => true, 'booking_id' => (int) $result['booking_id']]);
        } catch (\Throwable $e) {
            Log::error('Paymob callback finalize failed', [
                'transaction_id' => $transactionId,
                'merchant_order_id' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => true]);
        }
    }

    /**
     * @return array{booking_id:int}
     */
    private function createPaidBookingFromPayload(array $payload, array $payment): array
    {
        $sessionID = (int) ($payload['sessionID'] ?? 0);
        $spots = (int) ($payload['spots'] ?? 1);
        $promoCode = $payload['promoCode'] ?? null;
        $customerPayload = (array) ($payload['customer'] ?? []);

        $email = strtolower(trim((string) ($customerPayload['email'] ?? '')));
        if ($email === '') {
            throw new \RuntimeException('Missing customer email');
        }

        /** @var Customer $customer */
        $customer = Customer::query()->where('email', $email)->first();
        if (! $customer) {
            $customer = Customer::query()->create([
                'first_name' => (string) ($customerPayload['firstName'] ?? ''),
                'last_name' => (string) ($customerPayload['lastName'] ?? ''),
                'email' => $email,
                'phone' => (string) ($customerPayload['phone'] ?? ''),
                'source' => 'web',
                'timezone' => (string) config('app.timezone'),
            ]);
        } else {
            $customer->fill([
                'first_name' => (string) ($customerPayload['firstName'] ?? $customer->first_name),
                'last_name' => (string) ($customerPayload['lastName'] ?? $customer->last_name),
                'phone' => (string) ($customerPayload['phone'] ?? $customer->phone),
                'source' => $customer->source ?: 'web',
            ])->save();
        }

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings'])
            ->find($sessionID);

        if (! $appointment) {
            throw new \RuntimeException('Session not found');
        }

        $service = $appointment->service;
        $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
        $currentBookings = $appointment->bookings->whereIn('status', ['confirmed', 'pending'])->sum('party_size');
        if (($currentBookings + $spots) > $maxCapacity) {
            throw new \RuntimeException('Session is full');
        }

        $paidCents = (int) ($payment['amount_cents'] ?? 0);
        if ($paidCents <= 0) {
            throw new \RuntimeException('Missing paid amount');
        }
        $totalPrice = $paidCents / 100;

        $promoRecord = null;

        if ($promoCode) {
            $promoRecord = PromoCodeModel::resolveForCustomer(
                $promoCode,
                (int) $customer->id,
                PromoApplicableType::DropIns,
            );
            // Promo can become invalid between init and finalize (usage limit, etc).
            // We don't block booking creation if the user already paid successfully.
        }

        DB::beginTransaction();
        try {
            if ($promoRecord) {
                if (! $promoRecord->incrementUsageIfAllowed((int) $customer->id)) {
                    // Do not block booking creation after payment success; proceed without promo linkage.
                    $promoRecord = null;
                }
            }

            $booking = BookingModel::query()->create([
                'customer_id' => $customer->id,
                'appointment_id' => $appointment->id,
                'event_id' => null,
                'event_instance_id' => null,
                'package_id' => null,
                'customer_package_purchase_id' => null,
                'service_id' => $appointment->service_id,
                'provider_id' => $appointment->provider_id,
                'location_id' => $appointment->location_id,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'party_size' => $spots,
                'total_amount' => $totalPrice,
                'deposit_amount' => 0,
                'balance_amount' => 0,
                'currency' => (string) ($payment['currency'] ?? config('paymob.currency', 'EGP')),
                'channel' => 'web',
                'is_drop_in' => true,
                'answers' => [
                    'isDropIn' => true,
                    'spots' => $spots,
                    'guest' => true,
                ],
                'booked_at' => $appointment->booking_start,
            ]);

            PaymentModel::query()->create([
                'booking_id' => $booking->id,
                'promo_code_id' => $promoRecord?->id,
                'provider' => 'paymob',
                'provider_reference' => (string) ($payment['provider_reference'] ?? ''),
                'transaction_id' => (string) ($payment['transaction_id'] ?? ''),
                'status' => 'paid',
                'amount' => $totalPrice,
                'currency' => (string) ($payment['currency'] ?? config('paymob.currency', 'EGP')),
                'meta' => (array) ($payment['meta'] ?? []),
                'paid_at' => now(),
            ]);

            DB::commit();

            try {
                $this->sendBookingCreatedEmail(
                    $booking,
                    $appointment,
                    $customer,
                    $promoRecord,
                    null,
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return ['booking_id' => (int) $booking->id];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function sendBookingCreatedEmail(
        BookingModel $booking,
        AppointmentModel $appointment,
        Customer $customer,
        ?PromoCodeModel $promo = null,
        ?float $subtotalBeforePromo = null,
    ): void {
        if (! $customer->email) {
            return;
        }

        $service = $appointment->service;
        $provider = $appointment->provider;

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDate = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d');
        $appointmentTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i');

        $promoLines = $promo !== null
            ? PromoEmailText::appliedSection(
                $promo,
                $subtotalBeforePromo ?? (float) $booking->total_amount,
                (float) $booking->total_amount,
            )
            : '';

        $body = "Thank you for booking with Flexana!\n\n"
            . "Booking Details\n\n"
            . "* Name: {$customerName},\n\n"
            . "* Email: {$customer->email}\n\n"
            . "* Phone: {$customer->phone}\n\n"
            . "* Spots: " . ((int) ($booking->party_size ?? 1)) . "\n\n"
            . "* Class: " . ($service?->name ?? 'Unknown') . "\n\n"
            . "* Day: {$appointmentDate}\n\n"
            . "* Time: {$appointmentTime}\n\n"
            . "* Instructor: " . ($provider?->name ?? 'Unknown') . "\n\n"
            . "* Type: " . ($service?->description ?? '') . "\n\n"
            . "* Channel: website\n\n"
            . $promoLines
            . "If you need to cancel, please do so at least 24 hours in advance via your Flexana account or by contacting us directly.\n\n"
            . "You can contact us at +20 122 0221100 to reschedule your session or request a refund.\n\n"
            . "We look forward to seeing you on the mat!\n\n"
            . "Flexana Team";

        $subject = 'Your Flexana booking confirmation';

        InternalNotificationMail::sendCustomerAndInternalCopy(
            $body,
            $subject,
            $customer->email,
            $customerName,
        );
    }
}

