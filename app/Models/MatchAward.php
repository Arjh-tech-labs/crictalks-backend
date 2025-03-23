<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchAward extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'match_id',
        'user_id',
        'award_type',
        'description',
    ];

    /**
     * Get the match that the award belongs to.
     */
    public function match()
    {
        return $this->belongsTo(CricketMatch::class);
    }

    /**
     * Get the user who received the award.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

