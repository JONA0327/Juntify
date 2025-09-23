<?php

namespace App\Http\Controllers;

use App\Models\MercadoPagoPayment;
use App\Models\User;
use App\Services\UserPlans\UserPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\SDK;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->configureSdk();
    }

    public function createPreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:plan,addon'],
            'item_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0.1'],
            'currency' => ['nullable', 'string', 'max:10'],
            'success_url' => ['nullable', 'url'],
            'failure_url' => ['nullable', 'url'],
            'pending_url' => ['nullable', 'url'],
            'notification_url' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
        ]);

        $currency = $validated['currency'] ?? 'ARS';
        $externalReference = (string) Str::uuid();
        $notificationUrl = $validated['notification_url'] ?? config('services.mercado_pago.notification_url');

        if (! $notificationUrl) {
            $notificationUrl = route('payments.mercado-pago.webhook');
        }

        $preferenceData = [
            'items' => [
                [
                    'id' => $validated['item_id'] ?? $externalReference,
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? $validated['title'],
                    'quantity' => 1,
                    'unit_price' => (float) $validated['price'],
                    'currency_id' => $currency,
                ],
            ],
            'external_reference' => $externalReference,
            'notification_url' => $notificationUrl,
            'back_urls' => [
                'success' => $validated['success_url'] ?? url('/payments/success'),
                'pending' => $validated['pending_url'] ?? url('/payments/pending'),
                'failure' => $validated['failure_url'] ?? url('/payments/failure'),
            ],
            'auto_return' => 'approved',
            'metadata' => array_merge($validated['metadata'] ?? [], [
                'type' => $validated['type'],
                'item_id' => $validated['item_id'] ?? null,
            ]),
        ];

        try {
            $preference = $this->preferenceClient()->create($preferenceData);
        } catch (RuntimeException $exception) {
            Log::error('No se pudo crear la preferencia de Mercado Pago', [
                'message' => $exception->getMessage(),
            ]);

            abort(500, 'El servicio de pagos no está disponible.');
        }

        MercadoPagoPayment::create([
            'external_reference' => $externalReference,
            'preference_id' => $preference->id,
            'item_type' => $validated['type'],
            'item_id' => $validated['item_id'] ?? null,
            'item_name' => $validated['title'],
            'amount' => (float) $validated['price'],
            'currency' => $currency,
            'status' => MercadoPagoPayment::STATUS_PENDING,
            'metadata' => $preferenceData['metadata'],
        ]);

        return response()->json([
            'preference_id' => $preference->id,
            'init_point' => $preference->init_point,
            'sandbox_init_point' => $preference->sandbox_init_point ?? null,
            'external_reference' => $externalReference,
        ]);
    }

    public function showPreference(string $externalReference): JsonResponse
    {
        $payment = MercadoPagoPayment::where('external_reference', $externalReference)->firstOrFail();

        return response()->json($payment);
    }

    public function status(string $externalReference): JsonResponse
    {
        $payment = MercadoPagoPayment::where('external_reference', $externalReference)->firstOrFail();

        return response()->json([
            'external_reference' => $payment->external_reference,
            'status' => $payment->status,
            'payment_id' => $payment->payment_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'updated_at' => $payment->updated_at,
            'message' => $this->statusMessage($payment->status),
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $this->validateSignature($request);

        $payload = $request->json()->all();
        $paymentId = data_get($payload, 'data.id') ?? data_get($payload, 'id');

        if (! $paymentId) {
            Log::warning('Mercado Pago webhook without payment id', ['payload' => $payload]);

            return response()->json(['message' => 'ignored'], 202);
        }

        try {
            $paymentInfo = $this->paymentClient()->get($paymentId);
        } catch (RuntimeException $exception) {
            Log::error('No se pudo consultar el pago en Mercado Pago', [
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);

            abort(503, 'No fue posible obtener la información del pago.');
        }

        $externalReference = $paymentInfo->external_reference ?? null;
        $status = $paymentInfo->status ?? 'unknown';

        $normalizedMetadata = $this->normalizeMetadata($paymentInfo->metadata ?? null);

        $attributes = [
            'payment_id' => (string) $paymentInfo->id,
            'status' => $status,
            'amount' => $paymentInfo->transaction_amount ?? 0.0,
            'currency' => $paymentInfo->currency_id ?? 'ARS',
            'metadata' => $normalizedMetadata,
        ];

        $defaultDescription = data_get($normalizedMetadata, 'plan_name')
            ?? data_get($paymentInfo, 'description')
            ?? 'Mercado Pago payment';

        $itemId = data_get($normalizedMetadata, 'item_id') ?? data_get($paymentInfo, 'metadata.item_id');
        $itemType = data_get($normalizedMetadata, 'type') ?? data_get($paymentInfo, 'metadata.type') ?? 'plan';

        if ($externalReference) {
            $record = MercadoPagoPayment::updateOrCreate(
                ['external_reference' => $externalReference],
                array_merge($attributes, [
                    'item_name' => $defaultDescription,
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                ])
            );
        } else {
            $record = MercadoPagoPayment::updateOrCreate(
                ['payment_id' => (string) $paymentInfo->id],
                array_merge($attributes, [
                    'external_reference' => $paymentInfo->external_reference ?? (string) Str::uuid(),
                    'item_name' => $defaultDescription,
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                ])
            );
        }

        Log::info('Mercado Pago payment updated', [
            'external_reference' => $record->external_reference,
            'status' => $record->status,
        ]);

        if ($status === MercadoPagoPayment::STATUS_APPROVED) {
            $metadata = $normalizedMetadata ?? [];
            $userId = data_get($metadata, 'user_id') ?? data_get($paymentInfo, 'metadata.user_id');

            if (! $userId) {
                Log::warning('Pago aprobado sin user_id en metadata.', [
                    'payment_id' => $paymentInfo->id ?? null,
                ]);
            } else {
                $user = User::find($userId);

                if (! $user) {
                    Log::warning('No se encontró el usuario asociado al pago aprobado.', [
                        'user_id' => $userId,
                        'payment_id' => $paymentInfo->id ?? null,
                    ]);
                } elseif (empty($metadata)) {
                    Log::warning('Pago aprobado sin metadata para actualizar el plan.', [
                        'user_id' => $userId,
                        'payment_id' => $paymentInfo->id ?? null,
                    ]);
                } else {
                    $paidAt = data_get($paymentInfo, 'date_approved')
                        ?? data_get($paymentInfo, 'money_release_date')
                        ?? data_get($paymentInfo, 'date_last_updated')
                        ?? now()->toIso8601String();

                    app(UserPlanService::class)->activateFromPayment($user, $metadata, [
                        'provider' => 'mercado_pago',
                        'payment_id' => (string) $paymentInfo->id,
                        'external_reference' => $record->external_reference,
                        'status' => $status,
                        'amount' => $record->amount,
                        'currency' => $record->currency,
                        'paid_at' => $paidAt,
                        'metadata' => $metadata,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'processed']);
    }

    protected function statusMessage(?string $status): string
    {
        return match ($status) {
            'approved' => 'Tu pago fue acreditado correctamente. ¡Gracias por tu compra!',
            'pending', 'in_process' => 'Estamos revisando la información del pago. Te avisaremos cuando se acredite.',
            'rejected' => 'El pago fue rechazado. Revisa los datos ingresados e intenta nuevamente.',
            'cancelled' => 'El pago se canceló. Si fue un error puedes iniciar un nuevo intento.',
            default => 'Recibimos una actualización del pago. Revisa el detalle para más información.',
        };
    }

    protected function normalizeMetadata($metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            try {
                return json_decode(json_encode($metadata, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                Log::warning('No se pudo normalizar metadata de Mercado Pago', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function configureSdk(): void
    {
        if (! class_exists(SDK::class)) {
            Log::warning('Mercado Pago SDK is not installed. Install mercadopago/sdk to enable payments.');

            return;
        }

        if ($accessToken = config('services.mercado_pago.access_token')) {
            SDK::setAccessToken($accessToken);
        }

        if ($integratorId = config('services.mercado_pago.integrator_id')) {
            SDK::setIntegratorId($integratorId);
        }
    }

    protected function validateSignature(Request $request): void
    {
        $secret = config('services.mercado_pago.webhook_secret');

        if (! $secret) {
            return;
        }

        $signature = $request->header('x-signature');

        if (! $signature) {
            abort(401, 'Missing signature header');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature');
        }
    }

    protected function preferenceClient(): PreferenceClient
    {
        if (! class_exists(PreferenceClient::class)) {
            throw new RuntimeException('Mercado Pago SDK is required to create preferences.');
        }

        return new PreferenceClient();
    }

    protected function paymentClient(): PaymentClient
    {
        if (! class_exists(PaymentClient::class)) {
            throw new RuntimeException('Mercado Pago SDK is required to fetch payment information.');
        }

        return new PaymentClient();
    }
}
