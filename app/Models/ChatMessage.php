<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class ChatMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'chat_id',
        'sender_id',
        'body',
        'file_path',
        'drive_file_id',
        'original_name',
        'mime_type',
        'file_size',
        'preview_url',
        'voice_path',
        'created_at',
        'read_at',
    ];

    protected $dates = ['created_at', 'read_at'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBodyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}
