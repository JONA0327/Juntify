<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingContentContainer extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'meeting_content_containers';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'username',
        'group_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the user that owns the container.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    /**
     * Get the group that owns the container.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Get the meeting relations for the container.
     */
    public function meetingRelations(): HasMany
    {
        return $this->hasMany(MeetingContentRelation::class, 'container_id');
    }

    /**
     * Get the meetings associated with this container.
     */
    public function meetings()
    {
        return $this->hasManyThrough(
            TranscriptionLaravel::class,
            MeetingContentRelation::class,
            'container_id', // Foreign key on meeting_content_relations table
            'id',           // Foreign key on transcriptions_laravel table
            'id',           // Local key on meeting_content_containers table
            'meeting_id'    // Local key on meeting_content_relations table
        );
    }
}
