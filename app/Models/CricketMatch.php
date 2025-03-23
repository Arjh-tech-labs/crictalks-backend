<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CricketMatch extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tournament_id',
        'team1_id',
        'team2_id',
        'venue_id',
        'scheduled_date',
        'match_type',
        'status',
        'toss_winner_id',
        'toss_decision',
        'match_winner_id',
        'result_description',
        'team1_score',
        'team1_wickets',
        'team1_overs',
        'team2_score',
        'team2_wickets',
        'team2_overs',
        'player_of_match_id',
        'youtube_stream_id',
        'overlay_settings',
        'scorer_id',
        'streamer_id',
        'round',
        'match_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_date' => 'datetime',
        'overlay_settings' => 'json',
    ];

    /**
     * Get the tournament that the match belongs to.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the first team.
     */
    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    /**
     * Get the second team.
     */
    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    /**
     * Get the venue for the match.
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Get the toss winner.
     */
    public function tossWinner()
    {
        return $this->belongsTo(Team::class, 'toss_winner_id');
    }

    /**
     * Get the match winner.
     */
    public function matchWinner()
    {
        return $this->belongsTo(Team::class, 'match_winner_id');
    }

    /**
     * Get the player of the match.
     */
    public function playerOfMatch()
    {
        return $this->belongsTo(User::class, 'player_of_match_id');
    }

    /**
     * Get the scorer for the match.
     */
    public function scorer()
    {
        return $this->belongsTo(User::class, 'scorer_id');
    }

    /**
     * Get the streamer for the match.
     */
    public function streamer()
    {
        return $this->belongsTo(User::class, 'streamer_id');
    }

    /**
     * Get the innings for the match.
     */
    public function innings()
    {
        return $this->hasMany(Innings::class);
    }

    /**
     * Get the live stream settings for the match.
     */
    public function liveStreamSettings()
    {
        return $this->hasOne(LiveStreamSettings::class);
    }

    /**
     * Get the awards for the match.
     */
    public function awards()
    {
        return $this->hasMany(MatchAward::class);
    }
}

