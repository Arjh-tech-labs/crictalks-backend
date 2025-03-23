<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentTeam extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tournament_teams';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tournament_id',
        'team_id',
        'group',
        'points',
        'matches_played',
        'matches_won',
        'matches_lost',
        'matches_tied',
        'matches_no_result',
        'net_run_rate',
    ];

    /**
     * Get the tournament that the team is participating in.
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the team that is participating in the tournament.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}

