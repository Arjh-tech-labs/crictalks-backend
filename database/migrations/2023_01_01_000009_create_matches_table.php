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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('team1_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('team2_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('venue_id')->nullable()->constrained('venues')->onDelete('set null');
            $table->dateTime('scheduled_date');
            $table->string('match_type'); // T20, ODI, Test, etc.
            $table->string('status')->default('upcoming'); // upcoming, live, completed, abandoned
            $table->foreignId('toss_winner_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('toss_decision')->nullable(); // bat, bowl
            $table->foreignId('match_winner_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('result_description')->nullable();
            $table->integer('team1_score')->nullable();
            $table->integer('team1_wickets')->nullable();
            $table->float('team1_overs')->nullable();
            $table->integer('team2_score')->nullable();
            $table->integer('team2_wickets')->nullable();
            $table->float('team2_overs')->nullable();
            $table->foreignId('player_of_match_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('youtube_stream_id')->nullable();
            $table->json('overlay_settings')->nullable();
            $table->foreignId('scorer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('streamer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('round')->nullable(); // group stage, quarter-final, semi-final, final
            $table->string('match_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};

