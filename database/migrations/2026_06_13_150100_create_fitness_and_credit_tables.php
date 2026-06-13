<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fitness_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 30)->default('connected');
            $table->json('metadata')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'status']);
        });

        Schema::create('daily_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->date('log_date');
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('goal_steps')->default(10000);
            $table->decimal('distance_km', 10, 3)->default(0);
            $table->decimal('calories', 10, 2)->default(0);
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('active_minutes')->default(0);
            $table->unsignedInteger('step_credits_awarded')->default(0);
            $table->boolean('goal_bonus_awarded')->default(false);
            $table->json('source_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
            $table->index(['user_id', 'log_date']);
        });

        Schema::create('credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->unsignedBigInteger('lifetime_spent')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('reason', 50);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('step_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_step_log_id')->nullable()->constrained()->nullOnDelete();
            $table->date('boost_date');
            $table->unsignedInteger('boost_steps');
            $table->unsignedInteger('credits_spent')->default(0);
            $table->string('status', 30)->default('applied');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'boost_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_boosts');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_wallets');
        Schema::dropIfExists('daily_step_logs');
        Schema::dropIfExists('fitness_connections');
    }
};
