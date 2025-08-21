<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'remitente',
        'emisor',
        'status',
        'message',
        'type',
        'data',
    ];

    public $timestamps = true;

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor');
    }
}
