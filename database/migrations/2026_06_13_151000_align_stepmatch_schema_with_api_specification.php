<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
            $table->unsignedTinyInteger('age')->nullable()->change();
            $table->enum('gender', ['male', 'female', 'non_binary', 'other'])->nullable()->change();
            $table->renameColumn('photo_path', 'profile_photo');
            $table->string('city')->nullable()->after('longitude');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->boolean('profile_completed')->default(false)->after('country');
            $table->boolean('fitness_connected')->default(false)->after('profile_completed');
            $table->unsignedInteger('daily_step_goal')->default(10000)->after('fitness_connected');
            $table->enum('subscription_plan', ['free', 'gold', 'premium'])
                ->default('free')
                ->after('daily_step_goal');
            $table->dropColumn('profile_completed_at');
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->renameColumn('icon', 'emoji');
            $table->dropColumn('sort_order');
        });

        Schema::table('user_likes', function (Blueprint $table) {
            $table->enum('action', ['like', 'dislike', 'super_like'])->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->enum('type', ['text', 'audio', 'system', 'challenge'])
                ->default('text')
                ->change();
        });

        Schema::table('walking_challenges', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed', 'expired'])
                ->default('pending')
                ->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('plan', ['free', 'gold', 'premium'])->default('free')->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan', 30)->default('free')->change();
        });

        Schema::table('walking_challenges', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('type', 30)->default('text')->change();
        });

        Schema::table('user_likes', function (Blueprint $table) {
            $table->string('action', 30)->change();
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->renameColumn('emoji', 'icon');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'city',
                'state',
                'country',
                'profile_completed',
                'fitness_connected',
                'daily_step_goal',
                'subscription_plan',
            ]);
            $table->renameColumn('profile_photo', 'photo_path');
            $table->string('full_name')->nullable(false)->change();
            $table->unsignedTinyInteger('age')->nullable(false)->change();
            $table->string('gender', 50)->nullable(false)->change();
            $table->timestamp('profile_completed_at')->nullable();
        });
    }
};
