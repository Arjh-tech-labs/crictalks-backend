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
        Schema::create('balls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('innings_id')->constrained()->onDelete('cascade');
            $table->foreignId('bowler_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('batsman_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('non_striker_id')->constrained('users')->onDelete('cascade');
            $table->integer('over_number');
            $table->integer('ball_number');
            $table->integer('runs_scored');
            $table->boolean('is_wide')->default(false);
            $table->boolean('is_no_ball')->default(false);
            $table->boolean('is_bye')->default(false);
            $table->boolean('is_leg_bye')->default(false);
            $table->boolean('is_wicket')->default(false);
            $table->string('wicket_type')->nullable(); // bowled, caught, lbw, etc.
            $table->foreignId('wicket_player_out_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('wicket_fielder_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('commentary')->nullable();
            $table->json('wagon_wheel_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balls');
    }
};

