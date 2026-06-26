<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
    ];
}
