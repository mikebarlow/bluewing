<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('display_name');
            $table->string('external_identifier');
            $table->text('credentials_encrypted');
            $table->timestamps();

            $table->index(['provider']);
            $table->unique(['provider', 'external_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
