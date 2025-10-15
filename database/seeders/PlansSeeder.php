<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'freemium',
                'legacy_codes' => ['free'],
                'name' => 'Plan Free',
                'description' => 'Gratis para siempre',
                'price' => 0.00, // Gratuito
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Hasta 5 reuniones al mes',
                    'Duración máxima de 30 minutos por reunión',
                    '3 consultas al asistente y análisis de 1 documento por día',
                    'Subida de audio de hasta 50 MB',
                    'Transcripciones disponibles durante 7 días',
                    'Exportar documentos',
                    'Compartir reuniones'
                ]
            ],
            [
                'code' => 'basico',
                'legacy_codes' => ['basic'],
                'name' => 'Plan Basic',
                'description' => 'Pago mensual',
                'price' => 499.00, // Precio en pesos mexicanos
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Hasta 15 reuniones al mes',
                    'Duración máxima de 1 hora por reunión',
                    '10 consultas al asistente y análisis de hasta 5 documentos por día',
                    'Subida de audio de hasta 60 MB',
                    'Transcripciones disponibles durante 15 días',
                    'Exportar documentos',
                    'Compartir reuniones',
                    'Acceso a 3 contenedores para almacenar reuniones'
                ]
            ],
            [
                'code' => 'negocios',
                'legacy_codes' => ['business'],
                'name' => 'Negocios',
                'description' => 'Analítica avanzada y dashboards ejecutivos sin compromisos anuales.',
                'price' => 999.00, // Precio en pesos mexicanos
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Todo lo del Plan Basic',
                    'IA avanzada para análisis de conversaciones',
                    'Análisis de sentimientos y temas',
                    'Dashboards ejecutivos en tiempo real',
                    'Integraciones avanzadas',
                    'API personalizable',
                    'Capacitación incluida'
                ]
            ],
            [
                'code' => 'empresas',
                'legacy_codes' => ['enterprise'],
                'name' => 'Empresas',
                'description' => 'Control total, seguridad avanzada y soporte dedicado bajo demanda.',
                'price' => 2999.00, // Precio en pesos mexicanos
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Todo lo del plan Negocios',
                    'Implementación personalizada',
                    'Seguridad empresarial y cumplimiento',
                    'Análisis predictivo avanzado',
                    'Integraciones ilimitadas',
                    'Gerente de cuenta dedicado',
                    'SLA garantizado 99.9%'
                ]
            ]
        ];

        foreach ($plans as $planData) {
            $legacyCodes = $planData['legacy_codes'] ?? [];
            unset($planData['legacy_codes']);

            $plan = Plan::whereIn('code', array_merge([$planData['code']], $legacyCodes))->first();

            if ($plan) {
                $plan->fill($planData);
                $plan->code = $planData['code'];
                $plan->save();
            } else {
                Plan::create($planData);
            }
        }
    }
}
