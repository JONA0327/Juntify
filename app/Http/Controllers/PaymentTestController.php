<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentTestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Simular pago exitoso para desarrollo
     */
    public function simulateSuccess(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        // Crear external reference único
        $externalReference = 'sim_' . $plan->code . '_user_' . $user->id . '_' . time();

        // Crear registro de pago simulado exitoso
        $payment = Payment::create([
            'user_id' => $user->id, // Campo obligatorio
            'plan_id' => $plan->id, // Campo obligatorio
            'external_reference' => $externalReference,
            'external_payment_id' => 'sim_payment_' . uniqid(),
            'status' => 'approved', // Simular pago aprobado
            'amount' => $plan->price,
            'currency' => $plan->currency ?? 'MXN',
            'payment_method' => 'credit_card', // Método simulado
            'payer_email' => $user->email,
            'payer_name' => $user->name,
            'description' => "Plan {$plan->name} - Juntify (SIMULADO)",
            'webhook_data' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
                'simulated' => true,
                'simulation_time' => now()->toISOString(),
                'external_reference' => $externalReference
            ],
            'processed_at' => now()
        ]);

        Log::info('Payment simulated successfully', [
            'external_reference' => $externalReference,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'payment_id' => $payment->id
        ]);

        // Redirigir al perfil con confirmación de pago exitoso
        return redirect()->route('profile.show')->with([
            'payment_success' => true,
            'payment_plan' => $plan->name,
            'payment_amount' => $plan->price,
            'payment_currency' => $plan->currency ?? 'MXN',
            'payment_reference' => $externalReference,
            'payment_simulated' => true
        ])->with('success', '¡Pago procesado exitosamente! Tu suscripción ha sido activada.');
    }

    /**
     * Página de selección de simulación
     */
    public function selectPlan()
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        return view('payment-test.select-plan', compact('plans'));
    }
}
