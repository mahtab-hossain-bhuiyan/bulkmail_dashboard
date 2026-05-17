<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender',
        'recipient',
        'subject',
        'message_id',
        'status',
        'smtp_response',
        'mail_at',
    ];

    protected $casts = [
        'mail_at' => 'datetime',
    ];

    public function scopeForUser($query, string $senderEmail)
    {
        return $query->where('sender', $senderEmail);
    }

    public function scopeDateRange($query, ?string $start, ?string $end)
    {
        if ($start) {
            $query->where('mail_at', '>=', $start);
        }
        if ($end) {
            $query->where('mail_at', '<=', $end);
        }
        return $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }
}