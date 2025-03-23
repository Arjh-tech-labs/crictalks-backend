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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location');
            $table->string('format'); // T20, ODI, Test, etc.
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->foreignId('organizer_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('upcoming'); // upcoming, ongoing, completed
            $table->json('tournament_structure')->nullable(); // rounds, groups, etc.
            $table->json('rules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};

