<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class Group extends Model
{
    public const ROLE_INVITADO = 'invitado';
    public const ROLE_COLABORADOR = 'colaborador';
    public const ROLE_ADMINISTRADOR = 'administrador';
    public const ROLES = [
        self::ROLE_INVITADO,
        self::ROLE_COLABORADOR,
        self::ROLE_ADMINISTRADOR,
    ];

    protected $fillable = [
        'id_organizacion',
        'nombre_grupo',
        'descripcion',
        'miembros',
    ];

    protected $casts = [
        'id_organizacion' => 'integer',
        'miembros' => 'integer',
    ];

    protected static function booted()
    {
        static::deleting(function ($group) {
            foreach ($group->containers as $container) {
                $container->meetingRelations()->delete();
                $container->delete();
            }
            $group->code()->delete();
            $group->users()->detach();
        });

        static::created(function ($group) {
            try {
                $organization = $group->organization;
                if (!$organization) {
                    Log::warning('Group created without organization relation loaded', ['group_id' => $group->id]);
                    return;
                }
                // Resolver servicio vía container para no acoplar directamente
                $service = app(\App\Services\OrganizationDriveHierarchyService::class);
                $folder = $service->ensureGroupFolder($organization, $group);
                if ($folder && $folder->google_id) {
                    Log::info('Auto-created group drive folder', [
                        'group_id' => $group->id,
                        'org_id' => $organization->id,
                        'google_id' => $folder->google_id,
                    ]);
                } else {
                    Log::warning('Failed auto-create group folder after group creation', [
                        'group_id' => $group->id,
                        'org_id' => $organization->id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Exception auto-creating group folder', [
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'id_organizacion');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user', 'id_grupo', 'user_id')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function containers(): HasMany
    {
        return $this->hasMany(MeetingContentContainer::class, 'group_id');
    }

    public function code(): HasOne
    {
        return $this->hasOne(GroupCode::class, 'group_id');
    }
}
