<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingContentRelation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'meeting_content_relations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'container_id',
        'meeting_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the container that owns the relation.
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(MeetingContentContainer::class, 'container_id');
    }

    /**
     * Get the meeting that owns the relation.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }
}
