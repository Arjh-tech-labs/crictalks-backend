<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'city',
        'country',
        'address',
        'capacity',
        'image',
    ];

    /**
     * Get the matches played at the venue.
     */
    public function matches()
    {
        return $this->hasMany(CricketMatch::class);
    }
}

