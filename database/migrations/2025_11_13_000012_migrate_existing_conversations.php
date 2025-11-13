<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Attempt best-effort data migration from existing tables into the new unified tables.
        try {
            if (Schema::hasTable('conversations') && Schema::hasTable('conversation_messages')) {
                // Migrate legacy chats (one-to-one conversations)
                if (Schema::hasTable('chats') && Schema::hasTable('chat_messages')) {
                    $chats = DB::table('chats')->get();
                    foreach ($chats as $c) {
                        $convId = DB::table('conversations')->insertGetId([
                            'type' => 'chat',
                            'title' => null,
                            'user_one_id' => $c->user_one_id,
                            'user_two_id' => $c->user_two_id,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $messages = DB::table('chat_messages')->where('chat_id', $c->id)->orderBy('created_at')->get();
                        foreach ($messages as $m) {
                            DB::table('conversation_messages')->insert([
                                'conversation_id' => $convId,
                                'role' => null,
                                'sender_id' => $m->sender_id,
                                'body' => $m->body,
                                'drive_file_id' => $m->drive_file_id ?? null,
                                'original_name' => $m->original_name ?? null,
                                'mime_type' => $m->mime_type ?? null,
                                'file_size' => $m->file_size ?? null,
                                'preview_url' => $m->preview_url ?? null,
                                'voice_path' => $m->voice_path ?? null,
                                'read_at' => $m->read_at ?? null,
                                'created_at' => $m->created_at ?? now(),
                                'legacy_chat_message_id' => $m->id,
                            ]);
                        }
                    }
                }

                // Migrate AI chat sessions/messages
                if (Schema::hasTable('ai_chat_sessions') && Schema::hasTable('ai_chat_messages')) {
                    $sessions = DB::table('ai_chat_sessions')->get();
                    foreach ($sessions as $s) {
                        $convId = DB::table('conversations')->insertGetId([
                            'type' => 'ai',
                            'title' => $s->title,
                            'username' => $s->username,
                            'context_type' => $s->context_type,
                            'context_id' => $s->context_id,
                            'context_data' => $s->context_data ? json_encode(json_decode($s->context_data, true)) : null,
                            'is_active' => $s->is_active,
                            'last_activity' => $s->last_activity ?? null,
                            'created_at' => $s->created_at ?? now(),
                            'updated_at' => $s->updated_at ?? now(),
                        ]);

                        $messages = DB::table('ai_chat_messages')->where('session_id', $s->id)->orderBy('created_at')->get();
                        foreach ($messages as $m) {
                            DB::table('conversation_messages')->insert([
                                'conversation_id' => $convId,
                                'role' => $m->role,
                                'sender_id' => null,
                                'content' => $m->content,
                                'attachments' => $m->attachments ? json_encode(json_decode($m->attachments, true)) : null,
                                'metadata' => $m->metadata ? json_encode(json_decode($m->metadata, true)) : null,
                                'is_hidden' => $m->is_hidden ?? 0,
                                'created_at' => $m->created_at ?? now(),
                                'legacy_ai_message_id' => $m->id,
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Migration should not break deploys if something unexpected happens.
            DB::table('migrations')->insert([ 'migration' => '2025_11_13_migrate_existing_conversations_failed', 'batch' => 0 ]);
        }
    }

    public function down(): void
    {
        // We do not drop data on down to avoid accidental loss. Drop tables handled in earlier migrations.
    }
};
