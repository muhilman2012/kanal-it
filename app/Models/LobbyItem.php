<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LobbyItem extends Model
{
    protected $fillable = [
        'lobby_screen_id', 'title', 'kind', 'file_path', 'file_name', 'website_url',
        'fit', 'duration_seconds', 'sort_order', 'mode', 'weekdays',
        'starts_on', 'ends_on', 'starts_at', 'ends_at', 'exclusive', 'active',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'active' => 'boolean',
            'exclusive' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function screen()
    {
        return $this->belongsTo(LobbyScreen::class, 'lobby_screen_id');
    }
}
