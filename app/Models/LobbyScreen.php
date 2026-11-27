<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LobbyScreen extends Model
{
    protected $fillable = ['name', 'location', 'token_hash', 'token_encrypted', 'active', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(LobbyItem::class);
    }

    public function playerUrl(): string
    {
        return rtrim(config('app.url'), '/').'/layar-lobby/'.$this->token_encrypted;
    }
}
