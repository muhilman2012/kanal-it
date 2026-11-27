<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LobbyScreen extends Model
{
    protected $fillable = ['name', 'location', 'token_hash', 'token_encrypted', 'short_code', 'active', 'last_seen_at'];

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
        return rtrim(config('app.url'), '/').'/tv/'.$this->short_code;
    }

    public static function newShortCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (self::where('short_code', $code)->exists());
        return $code;
    }
}
