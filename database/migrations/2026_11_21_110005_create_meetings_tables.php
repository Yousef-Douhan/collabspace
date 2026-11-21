<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('reminder_minutes')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('key_points')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response', 20)->default('pending');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_user');
        Schema::dropIfExists('meetings');
    }
};
