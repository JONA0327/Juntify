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
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        // Verificar si el usuario ya tiene una suscripción activa
        $activeSubscription = $user->subscriptions()->active()->first();
        if ($activeSubscription) {
            return response()->json([
                'success' => false,
                'error' => 'Ya tienes una suscripción activa'
            ], 400);
        }

        $result = $this->mercadoPagoService->createPreferenceForPlan($plan, $user);

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
    }

    /**
     * Página de éxito del pago
     */
    public function success(Request $request)
    {
        $externalReference = $request->get('external_reference');
        $paymentStatus = null;

        if ($externalReference) {
            $paymentStatus = $this->mercadoPagoService->getPaymentStatus($externalReference);
        }

        return view('subscription.payment-success', compact('paymentStatus'));
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
