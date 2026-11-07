<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Meal;
use App\Models\Nutritionist;
use App\Models\NutritionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MealTrackingSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local', 'testing')) {
            throw new \RuntimeException(
                'Demo seeding is restricted to local/testing.'
            );
        }

        $athleteUser = User::firstOrCreate(
            ['email' => 'athlete@example.test'],
            [
                'name' => 'Demo Athlete',
                'password' => Hash::make('DemoPassword!123'),
            ]
        );

        $athleteUser->forceFill([
            'role' => 'athlete',
            'is_active' => true,
        ])->save();

        $otherUser = User::firstOrCreate(
            ['email' => 'other@example.test'],
            [
                'name' => 'Other Athlete',
                'password' => Hash::make('DemoPassword!123'),
            ]
        );

        $otherUser->forceFill([
            'role' => 'athlete',
            'is_active' => true,
        ])->save();

        $nutritionistUser = User::firstOrCreate(
            ['email' => 'nutritionist@example.test'],
            [
                'name' => 'Demo Nutritionist',
                'password' => Hash::make('DemoPassword!123'),
            ]
        );

        $nutritionistUser->forceFill([
            'role' => 'nutritionist',
            'is_active' => true,
        ])->save();

        $athlete = Athlete::firstOrCreate([
            'user_id' => $athleteUser->id,
        ]);

        Athlete::firstOrCreate([
            'user_id' => $otherUser->id,
        ]);

        $nutritionist = Nutritionist::firstOrCreate([
            'user_id' => $nutritionistUser->id,
        ]);

        $today = CarbonImmutable::now(
            config('meal_tracking.timezone')
        )->startOfDay();

        $plan = NutritionPlan::updateOrCreate(
            [
                'athlete_id' => $athlete->athlete_id,
                'title' => 'Demo Nutrition Plan',
            ],
            [
                'nutritionist_id' => $nutritionist->nutritionist_id,
                'daily_calories_target' => 2000,
                'start_date' => $today->subDays(7)->toDateString(),
                'end_date' => $today->addDays(30)->toDateString(),
                'is_active' => true,
            ]
        );

        Meal::updateOrCreate(
            [
                'nutrition_plan_id' => $plan->nutrition_plan_id,
                'name' => 'Demo Breakfast',
            ],
            [
                'meal_type' => 'breakfast',
                'description' => 'Example planned breakfast.',
                'calories' => 450,
            ]
        );
    }
}