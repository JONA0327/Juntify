<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompanyUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_panel_id',
        'user_id',
        'custom_role',
        'is_active',
        'joined_at',
        'added_by',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $companyUser) {
            if (empty($companyUser->{$companyUser->getKeyName()})) {
                $companyUser->{$companyUser->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Relación con el panel de la empresa
     */
    public function companyPanel(): BelongsTo
    {
        return $this->belongsTo(UserPanelAdministrativo::class, 'company_panel_id');
    }

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con quien agregó al usuario
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeForCompany($query, $companyPanelId)
    {
        return $query->where('company_panel_id', $companyPanelId);
    }
}
