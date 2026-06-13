<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Trails',
            'Coffee',
            'HIIT',
            'Running',
            'Tech',
            'Sunset',
            'Gear',
            'Fitness',
        ] as $name) {
            Interest::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }
    }
}
