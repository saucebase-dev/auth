<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table `laravel/passkeys` reads and writes. The package only publishes its
 * migration, so the module ships its own copy rather than asking every install to
 * publish it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
