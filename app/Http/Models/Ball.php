<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ball extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'innings_id',
        'bowler_id',
        'batsman_id',
        'non_striker_id',
        'over_number',
        'ball_number',
        'runs_scored',
        'is_wide',
        'is_no_ball',
        'is_bye',
        'is_leg_bye',
        'is_wicket',
        'wicket_type',
        'wicket_player_out_id',
        'wicket_fielder_id',
        'commentary',
        'wagon_wheel_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_wide' => 'boolean',
        'is_no_ball' => 'boolean',
        'is_bye' => 'boolean',
        'is_leg_bye' => 'boolean',
        'is_wicket' => 'boolean',
        'wagon_wheel_data' => 'json',
    ];

    /**
     * Get the innings that the ball belongs to.
     */
    public function innings()
    {
        return $this->belongsTo(Innings::class);
    }

    /**
     * Get the bowler.
     */
    public function bowler()
    {
        return $this->belongsTo(User::class, 'bowler_id');
    }

    /**
     * Get the batsman.
     */
    public function batsman()
    {
        return $this->belongsTo(User::class, 'batsman_id');
    }

    /**
     * Get the non-striker.
     */
    public function nonStriker()
    {
        return $this->belongsTo(User::class, 'non_striker_id');
    }

    /**
     * Get the player who got out.
     */
    public function playerOut()
    {
        return $this->belongsTo(User::class, 'wicket_player_out_id');
    }

    /**
     * Get the fielder who took the catch/run out.
     */
    public function fielder()
    {
        return $this->belongsTo(User::class, 'wicket_fielder_id');
    }
}

