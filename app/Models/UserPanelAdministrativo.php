<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserPanelAdministrativo extends Model
{
    protected $table = 'user_panel_administrativo';

    protected $fillable = [
        'id',
        'company_name',
        'administrator_id',
        'panel_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $panel) {
            if (empty($panel->{$panel->getKeyName()})) {
                $panel->{$panel->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(UserPanelMiembro::class, 'panel_id');
    }

    /**
     * Relación con los usuarios de la empresa
     */
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_panel_id');
    }

    /**
     * Usuarios activos de la empresa
     */
    public function activeCompanyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_panel_id')->where('is_active', true);
    }
}
