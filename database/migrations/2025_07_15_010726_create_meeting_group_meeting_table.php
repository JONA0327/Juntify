<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op migration: creation moved to 2025_08_04_000001_create_meeting_group_meeting_table.php
        // This prevents attempting to recreate the table if it already exists.
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_group_meeting');
    }
};
