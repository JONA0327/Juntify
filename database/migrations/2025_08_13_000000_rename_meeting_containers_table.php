<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_containers') && ! Schema::hasTable('containers')) {
            Schema::rename('meeting_containers', 'containers');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('containers') && ! Schema::hasTable('meeting_containers')) {
            Schema::rename('containers', 'meeting_containers');
        }
    }
};
