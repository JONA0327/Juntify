<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Core User Management Tables
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('current_organization_id')->unsigned()->nullable();
            $table->string('username')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('roles', 200)->nullable();
            $table->timestamp('legal_accepted_at')->nullable();
            $table->timestamps();
            $table->datetime('plan_expires_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->boolean('blocked_permanent')->default(false);
            $table->text('blocked_reason')->nullable();
            $table->uuid('blocked_by')->nullable();
            $table->string('plan', 50)->default('free');
            $table->string('plan_code', 50)->default('free');
            $table->boolean('is_role_protected')->default(false);

            $table->foreign('blocked_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('token');
            $table->datetime('expires_at');
            $table->boolean('used')->default(false);
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('email');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->uuid('remitente')->nullable();
            $table->uuid('emisor')->nullable();
            $table->string('status')->default('pending');
            $table->text('message');
            $table->string('type');
            $table->string('title');
            $table->json('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->uuid('from_user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('remitente')->references('id')->on('users')->onDelete('set null');
            $table->foreign('emisor')->references('id')->on('users')->onDelete('set null');
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. Organization & Group Management
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_organizacion');
            $table->text('descripcion')->nullable();
            $table->longText('imagen')->nullable();
            $table->integer('num_miembros')->default(0);
            $table->uuid('admin_id')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_organizacion');
            $table->string('nombre_grupo');
            $table->text('descripcion')->nullable();
            $table->integer('miembros')->default(0);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->foreign('id_organizacion')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id');
            $table->uuid('user_id');
            $table->string('rol');
            $table->timestamps();

            $table->primary(['organization_id', 'user_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->unsignedBigInteger('id_grupo');
            $table->uuid('user_id');
            $table->enum('rol', ['invitado', 'colaborador', 'administrador'])->default('invitado');
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->primary(['id_grupo', 'user_id']);
            $table->foreign('id_grupo')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('group_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('code', 6)->unique();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->unique(['group_id']);
        });

        // 3. Meeting Management
        Schema::create('meeting_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('owner_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('meeting_group_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_group_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->unique(['meeting_group_id', 'user_id']);
            $table->foreign('meeting_group_id')->references('id')->on('meeting_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('transcriptions_laravel', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('meeting_name');
            $table->string('transcript_drive_id')->nullable();
            $table->text('transcript_download_url');
            $table->string('audio_drive_id')->nullable();
            $table->text('audio_download_url');
            $table->timestamps();

            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
        });

        Schema::create('meeting_group_meeting', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_group_id');
            $table->unsignedBigInteger('meeting_id');
            $table->uuid('shared_by')->nullable();
            $table->timestamps();

            $table->unique(['meeting_group_id', 'meeting_id']);
            $table->foreign('meeting_group_id')->references('id')->on('meeting_groups')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
            $table->foreign('shared_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('meeting_content_containers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('username');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('meeting_content_relations', function (Blueprint $table) {
            $table->unsignedBigInteger('container_id');
            $table->unsignedBigInteger('meeting_id');
            $table->timestamps();

            $table->primary(['container_id', 'meeting_id']);
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
        });

        Schema::create('shared_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('meeting_id');
            $table->string('meeting_type', 20)->default('regular');
            $table->uuid('shared_by');
            $table->uuid('shared_with');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('shared_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('responded_at')->nullable();
            $table->text('message')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('shared_with')->references('id')->on('users')->onDelete('cascade');
        });

        // 4. Task Management
        Schema::create('tasks_laravel', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->unsignedBigInteger('meeting_id');
            $table->string('meeting_type', 20)->default('permanent');
            $table->string('tarea');
            $table->string('prioridad', 20)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->time('hora_limite')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('asignado')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('assignment_status', 30)->nullable();
            $table->tinyInteger('progreso')->unsigned()->default(0);
            $table->string('google_event_id')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'tarea']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('text');
            $table->text('description')->nullable();
            $table->string('assignee')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->string('priority')->nullable();
            $table->integer('progress')->default(0);
            $table->timestamps();
        });

        // 5. File & Folder Management
        Schema::create('google_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->datetime('expiry_date')->nullable();
            $table->integer('expires_in')->nullable();
            $table->text('scope')->nullable();
            $table->string('token_type', 50)->default('Bearer');
            $table->text('id_token')->nullable();
            $table->timestamp('token_created_at')->nullable();
            $table->text('recordings_folder_id')->nullable();
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->datetime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('google_token_id');
            $table->string('google_id')->unique()->nullable();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('google_token_id')->references('id')->on('google_tokens')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
        });

        Schema::create('subfolders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folder_id');
            $table->string('google_id')->unique()->nullable();
            $table->string('name');
            $table->timestamps();

            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
        });

        Schema::create('organization_google_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->datetime('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('organization_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('organization_google_token_id');
            $table->string('google_id')->unique()->nullable();
            $table->string('name');
            $table->timestamps();

            $table->foreign('organization_id', 'of_organization_id_foreign')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('organization_google_token_id', 'of_org_google_token_id_foreign')->references('id')->on('organization_google_tokens')->onDelete('cascade');
        });

        Schema::create('organization_subfolders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_folder_id');
            $table->string('google_id')->unique()->nullable();
            $table->string('name');
            $table->timestamps();

            $table->foreign('organization_folder_id', 'osf_org_folder_id_foreign')->references('id')->on('organization_folders')->onDelete('cascade');
        });

        Schema::create('organization_group_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('organization_folder_id')->nullable();
            $table->string('google_id')->unique();
            $table->string('name');
            $table->string('path_cached')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'ogf_organization_id_foreign')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id', 'ogf_group_id_foreign')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('organization_folder_id', 'ogf_org_folder_id_foreign')->references('id')->on('organization_folders')->onDelete('set null');
            $table->unique(['group_id']);
        });

        Schema::create('organization_container_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('container_id')->nullable();
            $table->unsignedBigInteger('organization_group_folder_id')->nullable();
            $table->string('google_id');
            $table->string('name');
            $table->string('path_cached')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'ocf_organization_id_foreign')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id', 'ocf_group_id_foreign')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('container_id', 'ocf_container_id_foreign')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('organization_group_folder_id', 'ocf_org_group_folder_id_foreign')->references('id')->on('organization_group_folders')->onDelete('set null');
        });

        Schema::create('container_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id');
            $table->uuid('user_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('drive_file_id')->nullable();
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('pending_folders', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('google_id')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // 6. Payment & Subscription Management
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('yearly_price', 12, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->tinyInteger('free_months')->unsigned()->default(0);
            $table->string('currency', 10)->default('ARS');
            $table->unsignedInteger('billing_cycle_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status', 32)->default('pending');
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->string('external_reference', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->unique(['user_id', 'plan_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('external_reference')->unique()->nullable();
            $table->string('external_payment_id')->unique()->nullable();
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('payment_method')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_name')->nullable();
            $table->text('description')->nullable();
            $table->json('webhook_data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('user_subscriptions')->onDelete('set null');
        });

        // 7. Usage Tracking & Limits
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
            $table->unsignedInteger('max_meetings_per_month')->nullable();
            $table->unsignedInteger('max_duration_minutes')->default(120);
            $table->boolean('allow_postpone')->default(true);
            $table->unsignedInteger('warn_before_minutes')->default(5);
            $table->timestamps();
        });

        Schema::create('monthly_meeting_usage', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('username')->nullable();
            $table->string('organization_id')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->integer('meetings_consumed')->default(0);
            $table->json('meeting_records')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id', 'year', 'month']);
        });

        Schema::create('ai_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->date('usage_date');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('document_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('limits', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('plan_code')->default('free');
            $table->string('role')->default('user');
            $table->integer('daily_message_limit')->default(10);
            $table->integer('daily_session_limit')->default(3);
            $table->boolean('can_upload_document')->default(false);
            $table->boolean('has_premium_features')->default(false);
            $table->json('additional_limits')->nullable();
            $table->timestamps();
        });

        // 8. AI & Conversation Management
        Schema::create('analyzers', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->text('system_prompt')->nullable();
            $table->text('user_prompt_template')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.30)->nullable();
            $table->tinyInteger('is_system')->default(0)->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('chat')->nullable();
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->unsignedBigInteger('user_one_id')->nullable();
            $table->unsignedBigInteger('user_two_id')->nullable();
            $table->string('context_type')->nullable();
            $table->string('context_id')->nullable();
            $table->json('context_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('role')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('content')->nullable();
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('preview_url')->nullable();
            $table->string('voice_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->unsignedBigInteger('legacy_chat_message_id')->nullable();
            $table->unsignedBigInteger('legacy_ai_message_id')->nullable();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
        });

        // 9. Additional Tables
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('contact_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('organization_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('container_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('target_user_id')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('transcription_temps', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('audio_path');
            $table->string('transcription_path');
            $table->unsignedBigInteger('audio_size');
            $table->decimal('duration', 10, 2)->default(0);
            $table->datetime('expires_at');
            $table->json('metadata')->nullable();
            $table->json('tasks')->nullable();
            $table->timestamps();
        });

        Schema::create('pending_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('meeting_name')->nullable();
            $table->string('audio_drive_id')->nullable();
            $table->text('audio_download_url')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();
            $table->string('backup_path')->nullable();
            $table->timestamps();
        });

        // Insert seed data for plan_limits
        DB::table('plan_limits')->insert([
            ['role' => 'free', 'max_meetings_per_month' => 3, 'max_duration_minutes' => 120, 'allow_postpone' => true, 'warn_before_minutes' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'basic', 'max_meetings_per_month' => 10, 'max_duration_minutes' => 180, 'allow_postpone' => true, 'warn_before_minutes' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'negocios', 'max_meetings_per_month' => 50, 'max_duration_minutes' => 300, 'allow_postpone' => true, 'warn_before_minutes' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'business', 'max_meetings_per_month' => 100, 'max_duration_minutes' => 480, 'allow_postpone' => true, 'warn_before_minutes' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'enterprise', 'max_meetings_per_month' => 999999, 'max_duration_minutes' => 600, 'allow_postpone' => true, 'warn_before_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'founder', 'max_meetings_per_month' => 999999, 'max_duration_minutes' => 600, 'allow_postpone' => true, 'warn_before_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'developer', 'max_meetings_per_month' => 999999, 'max_duration_minutes' => 600, 'allow_postpone' => true, 'warn_before_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'superadmin', 'max_meetings_per_month' => 999999, 'max_duration_minutes' => 999999, 'allow_postpone' => true, 'warn_before_minutes' => 60, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create the role protection trigger
        DB::unprepared('
            CREATE TRIGGER protect_role_before_update
            BEFORE UPDATE ON `users` FOR EACH ROW
            BEGIN
                IF OLD.is_role_protected = 1 AND (NEW.roles IS NOT NULL AND NEW.roles <> OLD.roles) THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Role change blocked: user is role-protected\';
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::unprepared('DROP TRIGGER IF EXISTS protect_role_before_update');

        // Drop tables in reverse order of dependencies
        Schema::dropIfExists('pending_recordings');
        Schema::dropIfExists('transcription_temps');
        Schema::dropIfExists('organization_activities');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('analyzers');
        Schema::dropIfExists('limits');
        Schema::dropIfExists('ai_daily_usage');
        Schema::dropIfExists('monthly_meeting_usage');
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('pending_folders');
        Schema::dropIfExists('container_files');
        Schema::dropIfExists('organization_container_folders');
        Schema::dropIfExists('organization_group_folders');
        Schema::dropIfExists('organization_subfolders');
        Schema::dropIfExists('organization_folders');
        Schema::dropIfExists('organization_google_tokens');
        Schema::dropIfExists('subfolders');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('google_tokens');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('tasks_laravel');
        Schema::dropIfExists('shared_meetings');
        Schema::dropIfExists('meeting_content_relations');
        Schema::dropIfExists('meeting_content_containers');
        Schema::dropIfExists('meeting_group_meeting');
        Schema::dropIfExists('transcriptions_laravel');
        Schema::dropIfExists('meeting_group_user');
        Schema::dropIfExists('meeting_groups');
        Schema::dropIfExists('group_codes');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
    }
};
