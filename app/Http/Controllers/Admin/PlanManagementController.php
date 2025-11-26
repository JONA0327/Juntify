<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanManagementController extends Controller
{
    private array $planTemplates = [
        'free' => [
            'label' => 'Free',
            'default_name' => 'Free',
            'description' => 'Gratis para siempre',
            'features' => [
                'Hasta 5 reuniones al mes',
                'Duración máxima de 30 minutos por reunión',
                '3 consultas al asistente y análisis de 1 documento por día',
                'Subida de audio de hasta 50 MB',
                'Transcripciones disponibles durante 7 días',
                'Exportar documentos',
                'Compartir reuniones',
            ],
        ],
        'basic' => [
            'label' => 'Basic',
            'default_name' => 'Basic',
            'description' => 'Pago mensual',
            'features' => [
                'Hasta 15 reuniones al mes',
                'Duración máxima de 1 hora por reunión',
                '10 consultas al asistente y análisis de hasta 5 documentos por día',
                'Subida de audio de hasta 60 MB',
                'Transcripciones disponibles durante 15 días',
                'Exportar documentos',
                'Compartir reuniones',
                'Acceso a 3 contenedores para almacenar reuniones',
            ],
        ],
        'business' => [
            'label' => 'Business',
            'default_name' => 'Business',
            'description' => 'Plan mensual',
            'features' => [
                'Hasta 30 reuniones al mes',
                'Duración máxima de 2 horas por reunión',
                'Subida de audio de hasta 100 MB',
                'Transcripciones y audios almacenados en Google Drive con transcripción encriptada',
                '25 consultas al asistente y análisis de hasta 10 documentos por día',
                'Acceso a herramienta de tareas',
                'Exportar documentos',
                'Compartir reuniones',
                'Acceso a 10 contenedores con máximo de 10 reuniones por contenedor',
                'Acceso al modo posponer reunión',
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'default_name' => 'Enterprise',
            'description' => 'Control total, seguridad avanzada y soporte dedicado bajo demanda.',
            'features' => [
                'Todo lo del plan Business',
                'Implementación personalizada',
                'Seguridad empresarial y cumplimiento',
                'Análisis predictivo avanzado',
                'Integraciones ilimitadas',
                'Gerente de cuenta dedicado',
                'SLA garantizado 99.9%',
            ],
        ],
    ];

    public function index()
    {
        return view('admin.plans', [
            'planTemplates' => $this->planTemplates,
        ]);
    }

    public function list(): JsonResponse
    {
        $plans = Plan::orderBy('code')->get()->map(function (Plan $plan) {
            return $this->formatPlan($plan);
        });

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code' => 'required|in:' . implode(',', array_keys($this->planTemplates)),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:95',
            'free_months' => 'nullable|integer|min:0|max:12',
            'is_active' => 'required|boolean',
        ]);

        $template = $this->planTemplates[$validated['plan_code']];

        $plan = Plan::firstOrNew(['code' => $validated['plan_code']]);
        $plan->fill([
            'name' => $validated['name'] ?? $template['default_name'],
            'description' => $validated['description'] ?? $template['description'] ?? null,
            'currency' => $validated['currency'] ?? 'MXN',
            'billing_cycle_days' => 30,
            'is_active' => $validated['is_active'],
            'features' => $plan->features ?: $template['features'],
        ]);

        $plan->price = $validated['monthly_price'];
        $plan->monthly_price = $validated['monthly_price'];
        $plan->yearly_price = $validated['yearly_price'] ?? null;
        $plan->discount_percentage = $validated['discount_percentage'] ?? 0;
        $plan->free_months = $validated['free_months'] ?? 0;

        $plan->save();

        Log::info('Plan guardado desde panel administrativo', [
            'plan_id' => $plan->id,
            'code' => $plan->code,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'discount_percentage' => $plan->discount_percentage,
            'free_months' => $plan->free_months,
        ]);

        return response()->json([
            'success' => true,
            'plan' => $this->formatPlan($plan),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'currency' => 'required|string|max:10',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:95',
            'free_months' => 'nullable|integer|min:0|max:12',
            'enabled' => 'required|boolean',
        ]);

        $plan->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'currency' => $validated['currency'],
            'price' => $validated['monthly_price'],
            'monthly_price' => $validated['monthly_price'],
            'yearly_price' => $validated['yearly_price'],
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'free_months' => $validated['free_months'] ?? 0,
            'is_active' => $validated['enabled'],
        ]);

        Log::info('Plan actualizado desde panel administrativo', [
            'plan_id' => $plan->id,
            'code' => $plan->code,
            'is_active' => $plan->is_active,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan actualizado exitosamente',
            'plan' => $this->formatPlan($plan->fresh()),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $planName = $plan->name;
        
        Log::info('Eliminando plan desde panel administrativo', [
            'plan_id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
        ]);

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => "Plan \"{$planName}\" eliminado exitosamente",
        ]);
    }

    private function formatPlan(Plan $plan): array
    {
        $yearlyBreakdown = $plan->getPriceBreakdown('yearly');

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'currency' => $plan->currency,
            'is_active' => (bool) $plan->is_active,
            'monthly_price' => $plan->getMonthlyPrice(),
            'yearly_price' => $yearlyBreakdown['price'],
            'yearly_base_price' => $yearlyBreakdown['yearly_base_price'],
            'discount_percentage' => $plan->discount_percentage,
            'free_months' => $plan->free_months,
            'features' => $plan->features,
            'updated_at' => optional($plan->updated_at)->toIso8601String(),
        ];
    }
}
