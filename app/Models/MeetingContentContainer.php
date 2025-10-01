<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

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

    protected static function booted()
    {
        static::created(function ($container) {
            try {
                $group = $container->group;
                if (!$group) {
                    Log::warning('Container created without group relation', ['container_id' => $container->id]);
                    return;
                }
                $organization = $group->organization;
                if (!$organization) {
                    Log::warning('Container group without organization', ['container_id' => $container->id, 'group_id' => $group->id]);
                    return;
                }
                $service = app(\App\Services\OrganizationDriveHierarchyService::class);
                $folder = $service->ensureContainerFolder($organization, $group, $container);
                if ($folder && $folder->google_id) {
                    Log::info('Auto-created container drive folder', [
                        'container_id' => $container->id,
                        'group_id' => $group->id,
                        'org_id' => $organization->id,
                        'google_id' => $folder->google_id,
                    ]);
                } else {
                    Log::warning('Failed auto-create container folder', [
                        'container_id' => $container->id,
                        'group_id' => $group->id,
                        'org_id' => $organization->id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Exception auto-creating container folder', [
                    'container_id' => $container->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

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
