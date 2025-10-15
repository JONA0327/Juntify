<?php

namespace App\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoService
{
    protected $accessToken;
    protected $preferenceClient;
    protected $paymentClient;

    public function __construct()
    {
        $this->accessToken = config('mercadopago.access_token');

        if (!$this->accessToken) {
            throw new \Exception('MercadoPago access token not configured');
        }

        MercadoPagoConfig::setAccessToken($this->accessToken);

        // Forzar configuración de sandbox para tokens de TEST
        if (str_starts_with($this->accessToken, 'TEST-')) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

            // Log para confirmar configuración
            Log::info('MercadoPago: Using TEST environment', [
                'token_prefix' => substr($this->accessToken, 0, 10) . '...',
                'environment' => 'SANDBOX/LOCAL'
            ]);
        } else {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);
            Log::info('MercadoPago: Using PRODUCTION environment', [
                'token_prefix' => substr($this->accessToken, 0, 10) . '...',
                'environment' => 'PRODUCTION/SERVER'
            ]);
        }

        $this->preferenceClient = new PreferenceClient();
        $this->paymentClient = new PaymentClient();
    }

    /**
     * Crear preferencia de pago para un plan
     */
    public function createPreferenceForPlan(Plan $plan, User $user): array
    {
        try {
            $externalReference = 'plan_' . $plan->code . '_user_' . $user->id . '_' . time();

            $preferenceData = [
                "items" => [
                    [
                        "id" => $plan->code,
                        "title" => "Plan " . $plan->name . " - Juntify",
                        "description" => $plan->description,
                        "quantity" => 1,
                        "unit_price" => (float) $plan->price,
                        "currency_id" => "MXN", // Usar peso mexicano
                        "category_id" => "digital_content"
                    ]
                ],
                "payer" => [
                    "name" => $user->full_name,
                    "email" => $user->email,
                    "address" => [
                        "zip_code" => "11000",
                        "street_name" => "Test Street"
                    ],
                    "identification" => [
                        "type" => "RFC",
                        "number" => "XAXX010101000"
                    ]
                ],
                "payment_methods" => [
                    "excluded_payment_methods" => [],
                    "excluded_payment_types" => [
                        ["id" => "ticket"] // Excluir pagos en efectivo para suscripciones
                    ],
                    "installments" => 12,
                ],
                "back_urls" => [
                    "success" => config('app.url') . '/payment/success',
                    "failure" => config('app.url') . '/payment/failure',
                    "pending" => config('app.url') . '/payment/pending'
                ],
                "external_reference" => $externalReference,
                // "notification_url" => "http://127.0.0.1:8000/webhook/mercadopago", // Comentado para desarrollo local
                "expires" => false,
                "site_id" => "MLM", // Forzar México
                "metadata" => [
                    "user_id" => $user->id,
                    "plan_id" => $plan->id,
                    "plan_code" => $plan->code
                ]
            ];

            // Log de la preferencia antes de crearla
            Log::info('MercadoPago: Creating preference with data', [
                'external_reference' => $externalReference,
                'plan_code' => $plan->code,
                'plan_price' => $plan->price,
                'plan_currency' => $plan->currency,
                'user_email' => $user->email,
                'site_id' => 'MLM',
                'access_token_prefix' => substr($this->accessToken, 0, 10) . '...'
            ]);

            $preference = $this->preferenceClient->create($preferenceData);

            // Log después de crear la preferencia
            Log::info('MercadoPago: Preference created successfully', [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point
            ]);

            // Crear registro de pago pendiente
            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'external_reference' => $externalReference,
                'status' => Payment::STATUS_PENDING,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'description' => "Plan {$plan->name} - Juntify"
            ]);

            Log::info('MercadoPago preference created', [
                'preference_id' => $preference->id,
                'external_reference' => $externalReference,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'payment_id' => $payment->id
            ]);

            return [
                'success' => true,
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
                'external_reference' => $externalReference,
                'payment_id' => $payment->id
            ];

        } catch (MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $responseData = null;

            if ($apiResponse) {
                try {
                    $responseContent = $apiResponse->getContent();
                    $responseData = is_string($responseContent) ? json_decode($responseContent, true) : $responseContent;
                } catch (\Exception $jsonError) {
                    $responseData = 'Could not parse response';
                }
            }

            Log::error('MercadoPago API Error creating preference', [
                'error' => $e->getMessage(),
                'response_data' => $responseData,
                'preference_data' => $preferenceData,
                'user_id' => $user->id,
                'plan_id' => $plan->id
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear la preferencia de pago: ' . $e->getMessage(),
                'details' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('General error creating MercadoPago preference', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $plan->id
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al procesar el pago'
            ];
        }
    }

    /**
     * Procesar notificación de webhook
     */
    public function processWebhookNotification(array $data): bool
    {
        try {
            Log::info('Processing MercadoPago webhook', ['data' => $data]);

            if (!isset($data['type']) || $data['type'] !== 'payment') {
                Log::info('Webhook ignored - not a payment notification', ['type' => $data['type'] ?? 'unknown']);
                return true;
            }

            $paymentId = $data['data']['id'] ?? null;
            if (!$paymentId) {
                Log::error('Webhook missing payment ID', ['data' => $data]);
                return false;
            }

            // Obtener información del pago desde MercadoPago
            $mpPayment = $this->paymentClient->get($paymentId);

            Log::info('MercadoPago payment details', [
                'payment_id' => $paymentId,
                'status' => $mpPayment->status,
                'external_reference' => $mpPayment->external_reference
            ]);

            // Buscar el pago en nuestra base de datos
            $payment = Payment::where('external_reference', $mpPayment->external_reference)->first();

            if (!$payment) {
                Log::error('Payment not found in database', [
                    'external_reference' => $mpPayment->external_reference,
                    'mp_payment_id' => $paymentId
                ]);
                return false;
            }

            // Actualizar información del pago
            $payment->update([
                'external_payment_id' => $paymentId,
                'status' => $mpPayment->status,
                'payment_method' => $mpPayment->payment_method_id,
                'payment_method_id' => $mpPayment->payment_method_id,
                'payer_email' => $mpPayment->payer->email ?? null,
                'payer_name' => $mpPayment->payer->first_name . ' ' . $mpPayment->payer->last_name ?? null,
                'webhook_data' => json_encode($mpPayment),
                'processed_at' => now()
            ]);

            // Si el pago fue aprobado, activar la suscripción
            if ($mpPayment->status === 'approved') {
                $this->activateSubscription($payment);
            }

            Log::info('Webhook processed successfully', [
                'payment_id' => $payment->id,
                'status' => $mpPayment->status
            ]);

            return true;

        } catch (MPApiException $e) {
            Log::error('MercadoPago API Error processing webhook', [
                'error' => $e->getMessage(),
                'api_response' => $e->getApiResponse(),
                'webhook_data' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('General error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_data' => $data
            ]);
            return false;
        }
    }

    /**
     * Activar suscripción después de pago aprobado
     */
    protected function activateSubscription(Payment $payment): void
    {
        try {
            $user = $payment->user;
            $plan = $payment->plan;

            // Crear nueva suscripción
            $subscription = $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->billing_cycle_days),
                'external_reference' => $payment->external_reference
            ]);

            // Actualizar el usuario
            $user->update([
                'roles' => $plan->code, // Actualizar rol según el plan
                'plan_expires_at' => now()->addDays($plan->billing_cycle_days)
            ]);

            // Asociar el pago con la suscripción
            $payment->update(['subscription_id' => $subscription->id]);

            Log::info('Subscription activated', [
                'user_id' => $user->id,
                'plan_code' => $plan->code,
                'subscription_id' => $subscription->id,
                'expires_at' => $user->plan_expires_at
            ]);

        } catch (\Exception $e) {
            Log::error('Error activating subscription', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Verificar estado de un pago
     */
    public function getPaymentStatus(string $externalReference): ?array
    {
        try {
            $payment = Payment::where('external_reference', $externalReference)->first();

            if (!$payment) {
                return null;
            }

            return [
                'payment' => $payment,
                'status' => $payment->status,
                'is_approved' => $payment->isApproved(),
                'is_pending' => $payment->isPending(),
                'is_rejected' => $payment->isRejected()
            ];

        } catch (\Exception $e) {
            Log::error('Error getting payment status', [
                'external_reference' => $externalReference,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
