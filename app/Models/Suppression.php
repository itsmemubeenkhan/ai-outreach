<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    protected $guarded = [];

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
