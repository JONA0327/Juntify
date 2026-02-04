<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DduAssistantConversation;
use App\Models\DduAssistantMessage;
use App\Models\DduAssistantDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DduAssistantController extends Controller
{
    /**
     * Listar conversaciones del usuario
     * GET /api/ddu/assistant/conversations?user_id=XXX
     */
    public function listConversations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
            ]);

            $conversations = DduAssistantConversation::where('user_id', $request->user_id)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($conv) {
                    return [
                        'id' => $conv->id,
                        'user_id' => $conv->user_id,
                        'title' => $conv->title,
                        'description' => $conv->description,
                        'messages_count' => $conv->messages()->count(),
                        'created_at' => $conv->created_at,
                        'updated_at' => $conv->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $conversations,
                'total' => $conversations->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al listar conversaciones DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conversaciones'
            ], 500);
        }
    }

    /**
     * Crear conversación
     * POST /api/ddu/assistant/conversations
     */
    public function createConversation(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|uuid',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            $conversation = DduAssistantConversation::create([
                'user_id' => $validated['user_id'],
                'title' => $validated['title'] ?? 'Nueva conversación',
                'description' => $validated['description'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'messages_count' => 0,
                    'created_at' => $conversation->created_at,
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al crear conversación DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear conversación'
            ], 500);
        }
    }

    /**
     * Obtener conversación con mensajes
     * GET /api/ddu/assistant/conversations/{id}?user_id=XXX
     */
    public function getConversation(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'metadata' => $msg->metadata,
                        'created_at' => $msg->created_at,
                    ];
                });

            $documents = $conversation->documents()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'mime_type' => $doc->mime_type,
                        'size' => $doc->size,
                        'summary' => $doc->summary,
                        'created_at' => $doc->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'messages' => $messages,
                    'documents' => $documents,
                    'messages_count' => $messages->count(),
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al obtener conversación DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conversación'
            ], 500);
        }
    }

    /**
     * Actualizar conversación
     * PUT /api/ddu/assistant/conversations/{id}
     */
    public function updateConversation(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|uuid',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $validated['user_id'])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $updateData = [];
            if (isset($validated['title'])) {
                $updateData['title'] = $validated['title'];
            }
            if (array_key_exists('description', $validated)) {
                $updateData['description'] = $validated['description'];
            }

            if (!empty($updateData)) {
                $conversation->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversación actualizada',
                'data' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'updated_at' => $conversation->updated_at,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar conversación DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar conversación'
            ], 500);
        }
    }

    /**
     * Eliminar conversación
     * DELETE /api/ddu/assistant/conversations/{id}?user_id=XXX
     */
    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            // Eliminar documentos del storage
            foreach ($conversation->documents as $document) {
                if (Storage::disk('local')->exists($document->path)) {
                    Storage::disk('local')->delete($document->path);
                }
            }

            // Eliminar conversación (cascade eliminará mensajes y documentos)
            $conversation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversación eliminada correctamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar conversación DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar conversación'
            ], 500);
        }
    }

    /**
     * Obtener mensajes de conversación
     * GET /api/ddu/assistant/conversations/{id}/messages?user_id=XXX
     */
    public function getMessages(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
                'limit' => 'nullable|integer|min:1|max:500',
                'offset' => 'nullable|integer|min:0',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);
            $total = $conversation->messages()->count();

            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'metadata' => $msg->metadata,
                        'created_at' => $msg->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $messages,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al obtener mensajes DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensajes'
            ], 500);
        }
    }

    /**
     * Agregar mensaje a conversación
     * POST /api/ddu/assistant/conversations/{id}/messages
     */
    public function addMessage(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|uuid',
                'role' => 'required|in:system,user,assistant,tool',
                'content' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $validated['user_id'])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $message = DduAssistantMessage::create([
                'assistant_conversation_id' => $conversation->id,
                'role' => $validated['role'],
                'content' => $validated['content'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Actualizar timestamp de la conversación
            $conversation->touch();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'assistant_conversation_id' => $message->assistant_conversation_id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at,
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al agregar mensaje DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar mensaje'
            ], 500);
        }
    }

    /**
     * Obtener documentos de conversación
     * GET /api/ddu/assistant/conversations/{id}/documents?user_id=XXX
     */
    public function getDocuments(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $documents = $conversation->documents()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'mime_type' => $doc->mime_type,
                        'size' => $doc->size,
                        'summary' => $doc->summary,
                        'created_at' => $doc->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $documents,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al obtener documentos DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos'
            ], 500);
        }
    }

    /**
     * Subir documento a conversación
     * POST /api/ddu/assistant/conversations/{id}/documents
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|uuid',
                'file' => 'required|file|max:51200', // 50MB max
                'extracted_text' => 'nullable|string',
                'summary' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $validated['user_id'])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            // Generar nombre único
            $filename = Str::uuid() . '.' . $extension;
            $path = 'assistant_documents/' . $validated['user_id'] . '/' . $filename;

            // Guardar archivo
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            $document = DduAssistantDocument::create([
                'assistant_conversation_id' => $conversation->id,
                'original_name' => $originalName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'extracted_text' => $validated['extracted_text'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Actualizar timestamp de la conversación
            $conversation->touch();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'assistant_conversation_id' => $document->assistant_conversation_id,
                    'original_name' => $document->original_name,
                    'path' => $document->path,
                    'mime_type' => $document->mime_type,
                    'size' => $document->size,
                    'extracted_text' => $document->extracted_text,
                    'summary' => $document->summary,
                    'created_at' => $document->created_at,
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al subir documento DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir documento'
            ], 500);
        }
    }

    /**
     * Eliminar documento
     * DELETE /api/ddu/assistant/conversations/{id}/documents/{docId}?user_id=XXX
     */
    public function deleteDocument(Request $request, int $id, int $docId): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|uuid',
            ]);

            $conversation = DduAssistantConversation::where('id', $id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            $document = DduAssistantDocument::where('id', $docId)
                ->where('assistant_conversation_id', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            // Eliminar archivo del storage
            if (Storage::disk('local')->exists($document->path)) {
                Storage::disk('local')->delete($document->path);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado correctamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar documento DDU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar documento'
            ], 500);
        }
    }
}
