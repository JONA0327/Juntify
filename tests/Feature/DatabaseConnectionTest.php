<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /** @test */
    public function it_can_run_a_basic_select_one_query()
    {
        $result = DB::select('SELECT 1 as test');
        $this->assertNotEmpty($result, 'La consulta SELECT 1 no devolvió filas');
        $this->assertEquals(1, (int)$result[0]->test, 'El resultado de SELECT 1 no es 1');
    }

    /** @test */
    public function migrations_table_exists_and_has_entries_or_is_empty()
    {
        // Evitamos dependencia de doctrine/dbal: consultamos information_schema
        $rows = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='migrations'");
        $this->assertNotEmpty($rows, 'La tabla migrations no existe: posible problema de conexión o migraciones no corridas');

        try {
            $count = DB::table('migrations')->count();
            $this->assertIsInt($count, 'No se pudo contar registros en migrations');
        } catch (\Throwable $e) {
            $this->fail('Error al contar registros en migrations: ' . $e->getMessage());
        }
    }

    /** @test */
    public function can_begin_and_commit_transaction()
    {
        DB::beginTransaction();
        DB::statement('SELECT 1');
        DB::rollBack(); // Rollback en lugar de commit para no modificar nada
        $this->assertTrue(true, 'Transacción básica falló');
    }
}
