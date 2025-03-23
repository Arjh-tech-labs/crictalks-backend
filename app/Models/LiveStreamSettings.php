<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveStreamSettings extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'match_id',
        'youtube_api_key',
        'youtube_channel_id',
        'youtube_stream_id',
        'stream_title',
        'stream_description',
        'stream_status',
        'scheduled_start_time',
        'overlay_settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_start_time' => 'datetime',
        'overlay_settings' => 'json',
    ];

    /**
     * Get the match that the live stream settings belong to.
     */
    public function match()
    {
        return $this->belongsTo(CricketMatch::class);
    }
}

