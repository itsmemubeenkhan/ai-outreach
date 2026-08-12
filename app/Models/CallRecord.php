<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'answered_at' => 'datetime', 'ended_at' => 'datetime', 'provider_metadata' => 'array'];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function session()
    {
        return $this->belongsTo(DialerSession::class, 'dialer_session_id');
    }
}
