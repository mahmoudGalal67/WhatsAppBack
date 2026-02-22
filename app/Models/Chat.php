<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = ['name', 'is_group'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
