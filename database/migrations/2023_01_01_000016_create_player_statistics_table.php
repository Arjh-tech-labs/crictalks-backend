<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('format'); // T20, ODI, Test, etc.
            
            // Batting stats
            $table->integer('matches_batted')->default(0);
            $table->integer('innings_batted')->default(0);
            $table->integer('runs_scored')->default(0);
            $table->integer('balls_faced')->default(0);
            $table->integer('not_outs')->default(0);
            $table->integer('highest_score')->default(0);
            $table->float('batting_average')->default(0);
            $table->float('batting_strike_rate')->default(0);
            $table->integer('fifties')->default(0);
            $table->integer('hundreds')->default(0);
            $table->integer('fours')->default(0);
            $table->integer('sixes')->default(0);
            
            // Bowling stats
            $table->integer('matches_bowled')->default(0);
            $table->integer('innings_bowled')->default(0);
            $table->float('overs_bowled')->default(0);
            $table->integer('runs_conceded')->default(0);
            $table->integer('wickets_taken')->default(0);
            $table->string('best_bowling_figures')->nullable();
            $table->float('bowling_average')->default(0);
            $table->float('economy_rate')->default(0);
            $table->float('bowling_strike_rate')->default(0);
            $table->integer('four_wickets')->default(0);
            $table->integer('five_wickets')->default(0);
            
            // Fielding stats
            $table->integer('catches')->default(0);
            $table->integer('stumpings')->default(0);
            $table->integer('run_outs')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};

