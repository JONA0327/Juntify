<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'name',
        'original_filename',
        'document_type',
        'mime_type',
        'file_size',
        'drive_file_id',
        'drive_folder_id',
        'drive_type',
        'extracted_text',
        'ocr_metadata',
        'processing_status',
        'processing_error',
        'document_metadata',
    ];

    protected $casts = [
        'ocr_metadata' => 'array',
        'document_metadata' => 'array',
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function meetingAssignments(): HasMany
    {
        return $this->hasMany(AiMeetingDocument::class, 'document_id');
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(AiTaskDocument::class, 'document_id');
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(AiContextEmbedding::class, 'content_id')->where('content_type', 'document_text');
    }

    public function scopeByUser($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function isProcessed(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function hasText(): bool
    {
        return !empty($this->extracted_text);
    }
}
