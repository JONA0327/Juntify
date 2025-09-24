<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteUserService
{
    /**
     * Elimina un usuario y todos sus datos relacionados.
     * - Reuniones (TranscriptionLaravel)
     * - Tareas (TaskLaravel, Task, AiTaskDocuments, etc.)
     * - Documentos AI, Embeddings, Chats, Mensajes, Contactos, Notificaciones
     * - Compartidos (SharedMeeting) tanto enviados como recibidos
     * - Tokens de Google y carpetas locales (Folder, Subfolder)
     * - (Opcional) intentará borrar carpeta raíz de Drive si config('drive.delete_root_on_user_delete')
     */
    public function deleteUser(User $user, bool $deleteDriveFolder = true): void
    {
        DB::transaction(function () use ($user, $deleteDriveFolder) {
            $username = $user->username;
            Log::info('DeleteUserService: iniciando eliminación de usuario', ['username' => $username]);

            // Shared meetings (enviados y recibidos)
            \App\Models\SharedMeeting::where('shared_by', $user->id)->orWhere('shared_with', $user->id)->delete();

            // Chats y mensajes
            \App\Models\ChatMessage::where('sender_id', $user->id)->delete();
            \App\Models\Chat::where('user_one_id', $user->id)->orWhere('user_two_id', $user->id)->delete();

            // Contactos
            \App\Models\Contact::where('user_id', $user->id)->orWhere('contact_id', $user->id)->delete();

            // Notificaciones
            \App\Models\Notification::where('user_id', $user->id)
                ->orWhere('remitente', $user->id)
                ->orWhere('emisor', $user->id)
                ->delete();

            // AI / Embeddings / Documents / Chat Sessions
            \App\Models\AiTaskDocument::where('assigned_by_username', $username)->delete();
            \App\Models\AiMeetingDocument::where('assigned_by_username', $username)->delete();
            \App\Models\AiDocument::where('username', $username)->delete();
            \App\Models\AiContextEmbedding::where('username', $username)->delete();
            \App\Models\AiChatSession::where('username', $username)->delete();

            // Tareas
            \App\Models\TaskLaravel::where('username', $username)->delete();
            \App\Models\Task::where('user_id', $username)->orWhere('assignee', $username)->delete();

            // Reuniones transcriptions y contenidos relacionados
            $meetingIds = \App\Models\TranscriptionLaravel::where('username', $username)->pluck('id');
            if ($meetingIds->isNotEmpty()) {
                // Key points, transcriptions, relations
                \App\Models\KeyPoint::whereIn('meeting_id', $meetingIds)->delete();
                \App\Models\Transcription::whereIn('meeting_id', $meetingIds)->delete();
                DB::table('meeting_content_relations')->whereIn('meeting_id', $meetingIds)->delete();
            }
            \App\Models\TranscriptionLaravel::where('username', $username)->delete();

            // Pending recordings / folders
            \App\Models\PendingRecording::where('username', $username)->delete();
            \App\Models\PendingFolder::where('username', $username)->delete();

            // Folders & subfolders
            $folderIds = \App\Models\Folder::whereHas('googleToken', function ($q) use ($username) { $q->where('username', $username); })->pluck('id');
            if ($folderIds->isNotEmpty()) {
                \App\Models\Subfolder::whereIn('folder_id', $folderIds)->delete();
            }
            \App\Models\Folder::whereIn('id', $folderIds)->delete();

            // Google token (y opcional borrar carpeta root en Drive)
            $token = \App\Models\GoogleToken::where('username', $username)->first();
            if ($token) {
                if ($deleteDriveFolder && $token->recordings_folder_id) {
                    try {
                        $driveService = app(\App\Services\GoogleDriveService::class);
                        $driveService->setAccessToken($token->getTokenArray());
                        $driveService->deleteFile($token->recordings_folder_id);
                    } catch (\Throwable $e) {
                        Log::warning('DeleteUserService: no se pudo borrar carpeta root en Drive', [
                            'username' => $username,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $token->delete();
            }

            // Planes / Suscripciones / Compras
            \App\Models\UserPlan::where('user_id', $user->id)->delete();
            \App\Models\UserSubscription::where('user_id', $user->id)->delete();
            \App\Models\PlanPurchase::where('user_id', $user->id)->delete();

            // Organización (si era admin) - opcional: podrías transferir o eliminar
            // Aquí simplemente nullificamos admin si coincide
            \App\Models\Organization::where('admin_id', $user->id)->update(['admin_id' => null]);
            \App\Models\OrganizationActivity::where('user_id', $user->id)->orWhere('target_user_id', $user->id)->delete();

            // Finalmente eliminar el usuario
            $user->delete();

            Log::info('DeleteUserService: usuario eliminado completamente', ['username' => $username]);
        });
    }
}
