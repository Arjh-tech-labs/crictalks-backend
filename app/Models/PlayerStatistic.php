<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerStatistic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'format',
        'matches_batted',
        'innings_batted',
        'runs_scored',
        'balls_faced',
        'not_outs',
        'highest_score',
        'batting_average',
        'batting_strike_rate',
        'fifties',
        'hundreds',
        'fours',
        'sixes',
        'matches_bowled',
        'innings_bowled',
        'overs_bowled',
        'runs_conceded',
        'wickets_taken',
        'best_bowling_figures',
        'bowling_average',
        'economy_rate',
        'bowling_strike_rate',
        'four_wickets',
        'five_wickets',
        'catches',
        'stumpings',
        'run_outs',
    ];

    /**
     * Get the user that the statistics belong to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

