<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 30);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'liked_user_id']);
            $table->index(['liked_user_id', 'action']);
        });

        Schema::create('step_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('match_percent')->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamp('matched_at')->useCurrent();
            $table->foreignId('unmatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unmatched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index(['user_one_id', 'status']);
            $table->index(['user_two_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_matches');
        Schema::dropIfExists('user_likes');
    }
};
