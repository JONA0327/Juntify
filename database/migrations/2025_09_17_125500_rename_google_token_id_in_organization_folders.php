<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Align database with code: rename organization_folders.google_token_id
        // to organization_google_token_id and set correct FK to organization_google_tokens(id)
        if (Schema::hasTable('organization_folders')
            && Schema::hasColumn('organization_folders', 'google_token_id')
            && ! Schema::hasColumn('organization_folders', 'organization_google_token_id')) {

            // Drop any existing foreign keys pointing from organization_folders.google_token_id
            $constraints = DB::select(<<<SQL
                SELECT CONSTRAINT_NAME AS name
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'organization_folders'
                  AND COLUMN_NAME = 'google_token_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL);

            foreach ($constraints as $c) {
                try {
                    DB::statement('ALTER TABLE `organization_folders` DROP FOREIGN KEY `'.$c->name.'`');
                } catch (\Throwable $e) {
                    // ignore if not exists
                }
            }

            // Rename the column
            DB::statement('ALTER TABLE `organization_folders` CHANGE `google_token_id` `organization_google_token_id` INT UNSIGNED NOT NULL');

            // Add the correct foreign key constraint to organization_google_tokens(id)
            try {
                DB::statement('ALTER TABLE `organization_folders` ADD CONSTRAINT `fk_org_folders_org_google_token` FOREIGN KEY (`organization_google_token_id`) REFERENCES `organization_google_tokens`(`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // If the FK already exists or cannot be created, log and continue
                // (Some environments may have missing table or different engine during dry runs.)
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organization_folders')
            && Schema::hasColumn('organization_folders', 'organization_google_token_id')
            && ! Schema::hasColumn('organization_folders', 'google_token_id')) {

            // Drop FK if present
            $constraints = DB::select(<<<SQL
                SELECT CONSTRAINT_NAME AS name
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'organization_folders'
                  AND COLUMN_NAME = 'organization_google_token_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL);
            foreach ($constraints as $c) {
                try {
                    DB::statement('ALTER TABLE `organization_folders` DROP FOREIGN KEY `'.$c->name.'`');
                } catch (\Throwable $e) {
                }
            }

            // Rename back
            DB::statement('ALTER TABLE `organization_folders` CHANGE `organization_google_token_id` `google_token_id` INT UNSIGNED NOT NULL');
        }
    }
};
