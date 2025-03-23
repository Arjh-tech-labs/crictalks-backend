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
        Schema::create('batsman_innings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('innings_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('runs_scored')->default(0);
            $table->integer('balls_faced')->default(0);
            $table->integer('fours')->default(0);
            $table->integer('sixes')->default(0);
            $table->string('dismissal_type')->nullable(); // bowled, caught, lbw, etc.
            $table->foreignId('bowler_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('fielder_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('batting_position');
            $table->boolean('is_out')->default(false);
            $table->string('status')->default('yet_to_bat'); // yet_to_bat, batting, out, retired_hurt, retired_not_out
            $table->json('wagon_wheel_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batsman_innings');
    }
};

