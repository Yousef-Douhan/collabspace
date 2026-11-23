<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Continue with GitHub / Google": provider ids and the provider's profile picture. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id', 64)->nullable()->unique()->after('password');
            $table->string('google_id', 64)->nullable()->unique()->after('github_id');
            $table->string('oauth_avatar', 500)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['github_id']);
            $table->dropUnique(['google_id']);
            $table->dropColumn(['github_id', 'google_id', 'oauth_avatar']);
        });
    }
};
