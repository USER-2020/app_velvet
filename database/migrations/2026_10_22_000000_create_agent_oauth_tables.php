<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_oauth_authorization_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code_hash', 64)->unique();
            $t->foreignId('user_id')->constrained('users');
            $t->uuid('installation_id');
            $t->string('hostname', 255);
            $t->string('studio_id', 100);
            $t->string('redirect_uri', 500);
            $t->string('code_challenge', 128);
            $t->string('code_challenge_method', 10);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
            $t->index(['installation_id', 'expires_at']);
        });

        Schema::create('agent_oauth_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('station_id')->constrained('agent_stations');
            $t->foreignId('user_id')->constrained('users');
            $t->string('access_token_hash', 64)->unique();
            $t->string('refresh_token_hash', 64)->unique();
            $t->timestamp('access_expires_at');
            $t->timestamp('refresh_expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['station_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_oauth_tokens');
        Schema::dropIfExists('agent_oauth_authorization_codes');
    }
};
