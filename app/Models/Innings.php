<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Innings extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'match_id',
        'batting_team_id',
        'bowling_team_id',
        'innings_number',
        'total_runs',
        'total_wickets',
        'total_overs',
        'extras',
        'byes',
        'leg_byes',
        'wides',
        'no_balls',
        'penalty_runs',
        'status',
    ];

    /**
     * Get the match that the innings belongs to.
     */
    public function match()
    {
        return $this->belongsTo(Match::class);
    }

    /**
     * Get the batting team.
     */
    public function battingTeam()
    {
        return $this->belongsTo(Team::class, 'batting_team_id');
    }

    /**
     * Get the bowling team.
     */
    public function bowlingTeam()
    {
        return $this->belongsTo(Team::class, 'bowling_team_id');
    }

    /**
     * Get the batsman innings for the innings.
     */
    public function batsmanInnings()
    {
        return $this->hasMany(BatsmanInnings::class);
    }

    /**
     * Get the bowler innings for the innings.
     */
    public function bowlerInnings()
    {
        return $this->hasMany(BowlerInnings::class);
    }

    /**
     * Get the balls for the innings.
     */
    public function balls()
    {
        return $this->hasMany(Ball::class);
    }
}

