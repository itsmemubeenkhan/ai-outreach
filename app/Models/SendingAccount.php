<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendingAccount extends Model
{
    protected $guarded = [];

    protected $hidden = ['smtp_password', 'imap_password'];

    protected function casts(): array
    {
        return ['smtp_password' => 'encrypted', 'imap_password' => 'encrypted', 'last_sent_at' => 'datetime', 'last_reset_at' => 'datetime', 'imap_last_checked_at' => 'datetime'];
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class)->withTimestamps();
    }

    public function outboundEmails()
    {
        return $this->hasMany(OutboundEmail::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
