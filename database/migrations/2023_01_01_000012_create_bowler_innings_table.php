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
        Schema::create('bowler_innings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('innings_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->float('overs')->default(0);
            $table->integer('maidens')->default(0);
            $table->integer('runs_conceded')->default(0);
            $table->integer('wickets')->default(0);
            $table->integer('wides')->default(0);
            $table->integer('no_balls')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bowler_innings');
    }
};

