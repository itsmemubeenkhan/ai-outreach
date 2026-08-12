<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['column_mapping' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function rejections()
    {
        return $this->hasMany(ImportRejection::class);
    }
}
