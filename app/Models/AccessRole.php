<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRole extends Model
{
    protected $fillable = ['name', 'permissions', 'is_admin'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_admin' => 'boolean'];
    }
}
