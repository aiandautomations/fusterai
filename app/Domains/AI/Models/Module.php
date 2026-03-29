<?php

namespace App\Domains\AI\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = ['alias', 'name', 'active', 'version', 'config'];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];
}
