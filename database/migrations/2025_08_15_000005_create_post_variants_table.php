<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type');
            $table->string('scope_value')->nullable();
            $table->text('body_text');
            $table->timestamps();

            $table->unique(['post_id', 'scope_type', 'scope_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_variants');
    }
};
