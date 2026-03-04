<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_message',
        'bot_reply',
        'session_id',
        'meta',
    ];

    /**
     * The attribute casting definitions.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'meta' => 'array',
    ];
}
