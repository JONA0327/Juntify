<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestMexicoCards extends Command
{
    protected $signature = 'test:mexico-cards';
    protected $description = 'Test different Mexico test cards for MercadoPago';

    public function handle()
    {
        $this->info('🇲🇽 Testing Mexico MercadoPago Cards');
        $this->info('=====================================');

        $mexicoCards = [
            [
                'number' => '4174 0005 1758 0553',
                'name' => 'APRO',
                'type' => 'Visa',
                'status' => 'Approved',
                'note' => 'Principal para México'
            ],
            [
                'number' => '5031 7557 3453 0604',
                'name' => 'APRO',
                'type' => 'Mastercard',
                'status' => 'Approved',
                'note' => 'Alternativa México'
            ],
            [
                'number' => '4009 1753 3280 7176',
                'name' => 'APRO',
                'type' => 'Visa',
                'status' => 'Approved',
                'note' => 'Otra opción México'
            ],
            [
                'number' => '5474 9254 3267 0366',
                'name' => 'APRO',
                'type' => 'Mastercard',
                'status' => 'Approved',
                'note' => 'Mastercard México'
            ],
            [
                'number' => '4013 5406 8274 6260',
                'name' => 'OTHE',
                'type' => 'Visa',
                'status' => 'Rejected',
                'note' => 'Para probar rechazo'
            ]
        ];

        foreach ($mexicoCards as $card) {
            $status = $card['status'] === 'Approved' ? '✅' : '❌';
            $this->line(sprintf(
                '%s %s %s (%s) - %s - %s',
                $status,
                $card['type'],
                $card['number'],
                $card['name'],
                $card['status'],
                $card['note']
            ));
        }

        $this->info('');
        $this->info('🔧 Datos completos para usar:');
        $this->info('CVV: 123');
        $this->info('Vencimiento: 11/30 (o cualquier fecha futura)');
        $this->info('');
        $this->warn('⚠️ Si ninguna funciona, el problema puede ser:');
        $this->warn('1. Configuración del site_id (debe ser MLM para México)');
        $this->warn('2. Token de acceso incorrecto');
        $this->warn('3. Problema temporal del sandbox de MercadoPago');
    }
}
