<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'location',
        'format',
        'logo',
        'banner',
        'organizer_id',
        'status',
        'tournament_structure',
        'rules',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tournament_structure' => 'json',
        'rules' => 'json',
    ];

    /**
     * Get the organizer of the tournament.
     */
    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the teams participating in the tournament.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'tournament_teams')
            ->withPivot('group', 'points', 'matches_played', 'matches_won', 'matches_lost', 'matches_tied', 'matches_no_result', 'net_run_rate')
            ->withTimestamps();
    }

    /**
     * Get the matches for the tournament.
     */
    public function matches()
    {
        return $this->hasMany(CricketMatch::class);
    }

    /**
     * Get the points table for the tournament.
     */
    public function pointsTable()
    {
        return $this->hasMany(TournamentTeam::class)
            ->orderBy('points', 'desc')
            ->orderBy('net_run_rate', 'desc');
    }
}

