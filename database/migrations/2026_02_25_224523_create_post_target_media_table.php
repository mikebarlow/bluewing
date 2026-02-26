<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_target_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_media_id')->constrained('post_media')->cascadeOnDelete();
            $table->string('provider_media_id')->nullable();
            $table->timestamps();

            $table->unique(['post_target_id', 'post_media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_target_media');
    }
};
