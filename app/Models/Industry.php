<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    protected $fillable = ['name', 'priority', 'default_score'];

    public function keywords()
    {
        return $this->hasMany(RootKeyword::class);
    }
}
