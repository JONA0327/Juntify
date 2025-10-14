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
                'code' => 'basico',
                'name' => 'Básico',
                'description' => 'Flexibilidad mes a mes para equipos medianos que buscan mejorar sus reuniones.',
                'price' => 999.00, // Precio en pesos mexicanos
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Reuniones ilimitadas',
                    'Transcripción avanzada con IA',
                    'Resúmenes inteligentes y tareas automáticas',
                    'Identificación de hablantes',
                    'Integraciones básicas',
                    'Soporte prioritario'
                ]
            ],
            [
                'code' => 'negocios',
                'name' => 'Negocios',
                'description' => 'Analítica avanzada y dashboards ejecutivos sin compromisos anuales.',
                'price' => 1999.00, // Precio en pesos mexicanos
                'currency' => 'MXN', // Peso mexicano
                'billing_cycle_days' => 30,
                'is_active' => true,
                'features' => [
                    'Todo lo del plan Básico',
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
                'name' => 'Empresas',
                'description' => 'Control total, seguridad avanzada y soporte dedicado bajo demanda.',
                'price' => 4999.00, // Precio en pesos mexicanos
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
            Plan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }
    }
}
