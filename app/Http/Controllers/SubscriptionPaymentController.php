<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Payment;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->middleware('auth');
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Mostrar planes disponibles - Redirige al perfil
     */
    public function index()
    {
        // Redirigir al perfil con la sección de planes
        return redirect('/profile')->with('navigateToPlans', true);
    }

    /**
     * Crear preferencia de pago para un plan
     */
    public function createPreference(Request $request)
    {
        Log::info('CreatePreference: Start', ['request' => $request->all()]);

        try {
            $request->validate([
                'plan_id' => 'required|exists:plans,id'
            ]);

            Log::info('CreatePreference: Validation passed');

            $plan = Plan::findOrFail($request->plan_id);
            $user = Auth::user();

            Log::info('CreatePreference: Plan and user loaded', [
                'plan' => $plan->toArray(),
                'user_id' => $user->id
            ]);

            // Verificar si el usuario ya tiene una suscripción activa
            // TODO: Implementar verificación de suscripción activa cuando esté disponible
            // $activeSubscription = $user->subscriptions()->active()->first();
            // if ($activeSubscription) {
            //     Log::info('CreatePreference: User has active subscription');
            //     return response()->json([
            //         'success' => false,
            //         'error' => 'Ya tienes una suscripción activa'
            //     ], 400);
            // }

            Log::info('CreatePreference: About to call MercadoPago service');
            $result = $this->mercadoPagoService->createPreferenceForPlan($plan, $user);
            Log::info('CreatePreference: MercadoPago service result', ['result' => $result]);

            // Log adicional para debugging
            Log::info('CreatePreference: URLs generated', [
                'init_point' => $result['init_point'] ?? 'N/A',
                'sandbox_init_point' => $result['sandbox_init_point'] ?? 'N/A',
                'using_checkout_url' => $result['sandbox_init_point'] ?? 'N/A'
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'preference_id' => $result['preference_id'],
                    'checkout_url' => $result['sandbox_init_point'], // Usar sandbox para desarrollo
                    'init_point' => $result['init_point'],
                    'sandbox_init_point' => $result['sandbox_init_point']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('CreatePreference: Exception caught', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Página de éxito del pago - Redirige al perfil con modal
     */
    public function success(Request $request)
    {
        $externalReference = $request->get('external_reference');
        $paymentStatus = null;

        // Obtener información del pago
        if ($externalReference) {
            $paymentStatus = $this->mercadoPagoService->getPaymentStatus($externalReference);
        }

        // Buscar el pago en la base de datos para obtener más detalles
        $payment = Payment::where('external_reference', $externalReference)->first();

        if ($payment && $payment->plan) {
            return redirect()->route('profile.show')->with([
                'payment_success' => true,
                'payment_plan' => $payment->plan->name,
                'payment_amount' => $payment->amount,
                'payment_currency' => $payment->currency,
                'payment_reference' => $externalReference,
                'payment_simulated' => $request->get('simulated', false)
            ])->with('success', '¡Pago procesado exitosamente! Tu suscripción ha sido activada.');
        }

        // Fallback si no se encuentra el pago
        return redirect()->route('profile.show')->with([
            'payment_success' => true,
            'payment_simulated' => $request->get('simulated', false)
        ])->with('success', '¡Pago procesado exitosamente!');
    }

    /**
     * Página de error del pago
     */
    public function failure(Request $request)
    {
        $externalReference = $request->get('external_reference');
        $paymentStatus = null;

        if ($externalReference) {
            $paymentStatus = $this->mercadoPagoService->getPaymentStatus($externalReference);
        }

        return view('subscription.payment-failure', compact('paymentStatus'));
    }

    /**
     * Página de pago pendiente
     */
    public function pending(Request $request)
    {
        $externalReference = $request->get('external_reference');
        $paymentStatus = null;

        if ($externalReference) {
            $paymentStatus = $this->mercadoPagoService->getPaymentStatus($externalReference);
        }

        return view('subscription.payment-pending', compact('paymentStatus'));
    }

    /**
     * Webhook de MercadoPago
     */
    public function webhook(Request $request)
    {
        Log::info('MercadoPago webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        try {
            $data = $request->all();
            $processed = $this->mercadoPagoService->processWebhookNotification($data);

            if ($processed) {
                return response()->json(['status' => 'success'], 200);
            } else {
                return response()->json(['status' => 'error'], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error processing MercadoPago webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Verificar estado de pago (AJAX)
     */
    public function checkPaymentStatus(Request $request)
    {
        $request->validate([
            'external_reference' => 'required|string'
        ]);

        $paymentStatus = $this->mercadoPagoService->getPaymentStatus($request->external_reference);

        if (!$paymentStatus) {
            return response()->json([
                'success' => false,
                'error' => 'Pago no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $paymentStatus['status'],
            'is_approved' => $paymentStatus['is_approved'],
            'is_pending' => $paymentStatus['is_pending'],
            'is_rejected' => $paymentStatus['is_rejected']
        ]);
    }

    /**
     * Ver historial de pagos del usuario
     */
    public function history()
    {
        $user = Auth::user();
        $payments = $user->payments()
            ->with(['plan', 'subscription'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('subscription.payment-history', compact('payments'));
    }
}
