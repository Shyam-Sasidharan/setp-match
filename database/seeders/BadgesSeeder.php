<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BadgesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->badges() as $badge) {
            Badge::query()->updateOrCreate(
                ['slug' => Str::slug($badge['name'])],
                [
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'category' => $badge['condition_type'],
                    'criteria' => [
                        'condition_type' => $badge['condition_type'],
                        'condition_value' => $badge['condition_value'],
                        'reward_credits' => $badge['reward_credits'],
                    ],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function badges(): array
    {
        return [
            [
                'name' => '100k Steps',
                'description' => 'Reach 100,000 lifetime steps.',
                'condition_type' => 'steps',
                'condition_value' => 100000,
                'reward_credits' => 100,
            ],
            [
                'name' => 'Explorer',
                'description' => 'Walk a total distance of 50 kilometres.',
                'condition_type' => 'distance',
                'condition_value' => 50,
                'reward_credits' => 50,
            ],
            [
                'name' => 'Green Lung',
                'description' => 'Complete a seven-day walking streak.',
                'condition_type' => 'streak',
                'condition_value' => 7,
                'reward_credits' => 50,
            ],
            [
                'name' => 'Early Bird',
                'description' => 'Complete 10,000 steps early in the day.',
                'condition_type' => 'early_bird',
                'condition_value' => 10000,
                'reward_credits' => 50,
            ],
            [
                'name' => '14 Day Streak',
                'description' => 'Complete a fourteen-day walking streak.',
                'condition_type' => 'streak',
                'condition_value' => 14,
                'reward_credits' => 100,
            ],
        ];
    }
}
