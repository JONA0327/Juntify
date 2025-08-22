<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;

$columns = DB::select('SHOW COLUMNS FROM group_user WHERE Field = "rol"');
echo 'Columna rol: ' . $columns[0]->Type . PHP_EOL;

echo 'Probando inserción con administrador...' . PHP_EOL;
try {
    DB::table('group_user')->insert([
        'user_id' => '58c833cb-df4c-4129-a88a-72aaa3b2254e',
        'id_grupo' => 999,
        'rol' => 'administrador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo 'OK - Inserción exitosa con rol administrador' . PHP_EOL;

    // Eliminar el registro de prueba
    DB::table('group_user')->where('id_grupo', 999)->delete();
    echo 'Registro de prueba eliminado' . PHP_EOL;

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
