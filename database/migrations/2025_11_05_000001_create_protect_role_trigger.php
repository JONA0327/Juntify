<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Crear trigger para evitar que se cambie el rol si is_role_protected = 1
        $sql = <<<'SQL'
CREATE TRIGGER protect_role_before_update
BEFORE UPDATE ON `users` FOR EACH ROW
BEGIN
    IF OLD.is_role_protected = 1 AND (NEW.roles IS NOT NULL AND NEW.roles <> OLD.roles) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role change blocked: user is role-protected';
    END IF;
END;
SQL;
        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS protect_role_before_update');
    }
};
