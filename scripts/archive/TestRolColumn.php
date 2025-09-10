<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestRolColumn extends Command
{
    protected $signature = 'test:rol-column';
    protected $description = 'Test rol column in group_user table';

    public function handle()
    {
        $columns = DB::select('SHOW COLUMNS FROM group_user WHERE Field = "rol"');
        $this->info('Columna rol: ' . $columns[0]->Type);

        $this->info('Probando inserción con administrador...');
        try {
            DB::table('group_user')->insert([
                'user_id' => '58c833cb-df4c-4129-a88a-72aaa3b2254e',
                'id_grupo' => 999,
                'rol' => 'administrador',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info('OK - Inserción exitosa con rol administrador');

            // Eliminar el registro de prueba
            DB::table('group_user')->where('id_grupo', 999)->delete();
            $this->info('Registro de prueba eliminado');

        } catch (\Exception $e) {
            $this->error('ERROR: ' . $e->getMessage());
        }
    }
}
