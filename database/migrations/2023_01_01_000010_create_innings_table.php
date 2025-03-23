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
        Schema::create('innings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->foreignId('batting_team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('bowling_team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('innings_number');
            $table->integer('total_runs')->default(0);
            $table->integer('total_wickets')->default(0);
            $table->float('total_overs')->default(0);
            $table->integer('extras')->default(0);
            $table->integer('byes')->default(0);
            $table->integer('leg_byes')->default(0);
            $table->integer('wides')->default(0);
            $table->integer('no_balls')->default(0);
            $table->integer('penalty_runs')->default(0);
            $table->string('status')->default('upcoming'); // upcoming, ongoing, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('innings');
    }
};

