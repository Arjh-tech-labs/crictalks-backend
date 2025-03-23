<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'city',
        'location',
        'profile_picture',
        'password',
        'firebase_uid',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the user types for the user.
     */
    public function userTypes()
    {
        return $this->belongsToMany(UserType::class)
            ->withPivot('profile_data')
            ->withTimestamps();
    }

    /**
     * Check if the user has a specific user type.
     */
    public function hasUserType($type)
    {
        return $this->userTypes()->where('name', $type)->exists();
    }

    /**
     * Get the teams that the user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role', 'jersey_number', 'batting_style', 'bowling_style', 'player_type')
            ->withTimestamps();
    }

    /**
     * Get the teams managed by the user.
     */
    public function managedTeams()
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    /**
     * Get the tournaments organized by the user.
     */
    public function organizedTournaments()
    {
        return $this->hasMany(Tournament::class, 'organizer_id');
    }

    /**
     * Get the matches where the user is the player of the match.
     */
    public function playerOfMatchAwards()
    {
        return $this->hasMany(CricketMatch::class, 'player_of_match_id');
    }

    /**
     * Get the matches scored by the user.
     */
    public function scoredMatches()
    {
        return $this->hasMany(CricketMatch::class, 'scorer_id');
    }

    /**
     * Get the matches streamed by the user.
     */
    public function streamedMatches()
    {
        return $this->hasMany(CricketMatch::class, 'streamer_id');
    }

    /**
     * Get the batsman innings for the user.
     */
    public function batsmanInnings()
    {
        return $this->hasMany(BatsmanInnings::class);
    }

    /**
     * Get the bowler innings for the user.
     */
    public function bowlerInnings()
    {
        return $this->hasMany(BowlerInnings::class);
    }

    /**
     * Get the milestones achieved by the user.
     */
    public function milestones()
    {
        return $this->hasMany(PlayerMilestone::class);
    }

    /**
     * Get the awards received by the user.
     */
    public function awards()
    {
        return $this->hasMany(MatchAward::class);
    }

    /**
     * Get the statistics for the user.
     */
    public function statistics()
    {
        return $this->hasMany(PlayerStatistic::class);
    }

    /**
     * Get the overlay templates created by the user.
     */
    public function overlayTemplates()
    {
        return $this->hasMany(OverlayTemplate::class, 'created_by');
    }
}

