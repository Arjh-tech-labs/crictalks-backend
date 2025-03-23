<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatsmanInnings extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'innings_id',
        'user_id',
        'runs_scored',
        'balls_faced',
        'fours',
        'sixes',
        'dismissal_type',
        'bowler_id',
        'fielder_id',
        'batting_position',
        'is_out',
        'status',
        'wagon_wheel_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_out' => 'boolean',
        'wagon_wheel_data' => 'json',
    ];

    /**
     * Get the innings that the batsman innings belongs to.
     */
    public function innings()
    {
        return $this->belongsTo(Innings::class);
    }

    /**
     * Get the user (batsman).
     */
    public function batsman()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the bowler who dismissed the batsman.
     */
    public function bowler()
    {
        return $this->belongsTo(User::class, 'bowler_id');
    }

    /**
     * Get the fielder who caught/run out the batsman.
     */
    public function fielder()
    {
        return $this->belongsTo(User::class, 'fielder_id');
    }
}

