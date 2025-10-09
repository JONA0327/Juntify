<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserPanelMiembro extends Model
{
    protected $table = 'user_panel_miembros';

    protected $fillable = [
        'id',
        'panel_id',
        'user_id',
        'role',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            if (empty($member->{$member->getKeyName()})) {
                $member->{$member->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    public function panel(): BelongsTo
    {
        return $this->belongsTo(UserPanelAdministrativo::class, 'panel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
