<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use App\Models\GoogleToken;
use App\Models\UserPanelAdministrativo;
use App\Models\UserPanelMiembro;
use App\Models\CompanyUser;

class User extends Authenticatable
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'username',
        'full_name',
        'email',
        'password',
        'roles',           // ahora un string
        'current_organization_id',
        'plan_expires_at',
        'blocked_at',
        'blocked_until',
        'blocked_permanent',
        'blocked_reason',
        'blocked_by',
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'blocked_at' => 'datetime',
        'blocked_until' => 'datetime',
        'blocked_permanent' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($u) {
            if (empty($u->{$u->getKeyName()})) {
                $u->{$u->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function googleToken(): HasOne
    {
        return $this->hasOne(GoogleToken::class, 'username', 'username');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user', 'user_id', 'organization_id')
                    ->withPivot('rol')
                    ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user', 'user_id', 'id_grupo');
    }

    public function organizationFolder(): HasOne
    {
        return $this->hasOne(OrganizationFolder::class, 'organization_id', 'current_organization_id');
    }

    public function organizationSubfolders(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrganizationSubfolder::class,
            OrganizationFolder::class,
            'organization_id',      // Foreign key on organization_folders table
            'organization_folder_id', // Foreign key on organization_subfolders table
            'current_organization_id', // Local key on users table
            'id'                     // Local key on organization_folders table
        );
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function contactOf(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(UserPlan::class);
    }

    public function currentPlan(): HasOne
    {
        return $this->hasOne(UserPlan::class)->current();
    }

    public function planPurchases(): HasManyThrough
    {
        return $this->hasManyThrough(PlanPurchase::class, UserPlan::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'blocked_by');
    }

    public function administeredPanels(): HasMany
    {
        return $this->hasMany(UserPanelAdministrativo::class, 'administrator_id');
    }

    public function panelMemberships(): HasMany
    {
        return $this->hasMany(UserPanelMiembro::class, 'user_id');
    }

    /**
     * Empresas donde el usuario está registrado
     */
    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    /**
     * Empresas activas donde el usuario está registrado
     */
    public function activeCompanyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id')->where('is_active', true);
    }

    public function isBlocked(): bool
    {
        if ($this->blocked_permanent) {
            return true;
        }

        if ($this->blocked_until instanceof Carbon) {
            return $this->blocked_until->isFuture();
        }

        return false;
    }

    public function blockingEndsAt(): ?Carbon
    {
        if ($this->blocked_permanent) {
            return null;
        }

        return $this->blocked_until;
    }
}
