<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BowlerInnings extends Model
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
        'overs',
        'maidens',
        'runs_conceded',
        'wickets',
        'wides',
        'no_balls',
    ];

    /**
     * Get the innings that the bowler innings belongs to.
     */
    public function innings()
    {
        return $this->belongsTo(Innings::class);
    }

    /**
     * Get the user (bowler).
     */
    public function bowler()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

