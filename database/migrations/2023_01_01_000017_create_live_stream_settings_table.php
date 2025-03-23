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
        Schema::create('live_stream_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->string('youtube_api_key')->nullable();
            $table->string('youtube_channel_id')->nullable();
            $table->string('youtube_stream_id')->nullable();
            $table->string('stream_title')->nullable();
            $table->text('stream_description')->nullable();
            $table->string('stream_status')->default('not_started'); // not_started, scheduled, live, completed
            $table->dateTime('scheduled_start_time')->nullable();
            $table->json('overlay_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_settings');
    }
};

