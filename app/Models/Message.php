<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'type',
        'body',
        'file_path',
        'reply_to',
        'forwarded_from',
        'is_delivered',
        'is_seen',
        'delivered_at',
        'seen_at',
        'is_deleted',
        'deleted_for',
    ];


    public function getWhatsappTimeAttribute()
    {
        $date = $this->created_at;

        if ($date->isToday()) {
            return $date->format('H:i'); // 14:32
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        if ($date->isCurrentWeek()) {
            return $date->format('l'); // Monday
        }

        return $date->format('d/m/Y'); // 12/02/2026
    }

    protected $appends = ['whatsapp_time'];


    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replyMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(Message::class, 'forwarded_from');
    }
}
