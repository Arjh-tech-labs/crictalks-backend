<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'logo',
        'city',
        'description',
        'manager_id',
    ];

    /**
     * Get the manager of the team.
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the players for the team.
     */
    public function players()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'jersey_number', 'batting_style', 'bowling_style', 'player_type')
            ->withTimestamps();
    }

    /**
     * Get the tournaments that the team is participating in.
     */
    public function tournaments()
    {
        return $this->belongsToMany(Tournament::class, 'tournament_teams')
            ->withPivot('group', 'points', 'matches_played', 'matches_won', 'matches_lost', 'matches_tied', 'matches_no_result', 'net_run_rate')
            ->withTimestamps();
    }

    /**
     * Get the home matches for the team.
     */
    public function homeMatches()
    {
        return $this->hasMany(Match::class, 'team1_id');
    }

    /**
     * Get the away matches for the team.
     */
    public function awayMatches()
    {
        return $this->hasMany(Match::class, 'team2_id');
    }

    /**
     * Get all matches for the team.
     */
    public function matches()
    {
        return Match::where('team1_id', $this->id)
            ->orWhere('team2_id', $this->id);
    }

    /**
     * Get the batting innings for the team.
     */
    public function battingInnings()
    {
        return $this->hasMany(Innings::class, 'batting_team_id');
    }

    /**
     * Get the bowling innings for the team.
     */
    public function bowlingInnings()
    {
        return $this->hasMany(Innings::class, 'bowling_team_id');
    }
}

