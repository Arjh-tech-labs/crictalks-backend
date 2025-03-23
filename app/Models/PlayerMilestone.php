<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMilestone extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'milestone_type',
        'milestone_value',
        'match_id',
        'achieved_at',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'achieved_at' => 'datetime',
    ];

    /**
     * Get the user who achieved the milestone.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the match where the milestone was achieved.
     */
    public function match()
    {
        return $this->belongsTo(CricketMatch::class);
    }
}

