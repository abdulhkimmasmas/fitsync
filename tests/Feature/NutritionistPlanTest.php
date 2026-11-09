<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\MealLog;
use App\Models\NutritionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MealTrackingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NutritionistPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $athleteUser;

    private User $otherUser;

    private User $nutritionistUser;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MealTrackingSeeder::class);

        $this->athleteUser = User::where('email', 'athlete@example.test')->firstOrFail();
        $this->otherUser = User::where('email', 'other@example.test')->firstOrFail();
        $this->nutritionistUser = User::where('email', 'nutritionist@example.test')->firstOrFail();

        $this->today = CarbonImmutable::now(config('meal_tracking.timezone'))->startOfDay();
    }

    /** Plan for the "other" athlete, far from the seeded demo plan dates. */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'athlete_id' => $this->otherUser->athlete->athlete_id,
            'title' => 'Cutting Plan',
            'daily_calories_target' => 1800,
            'start_date' => $this->today->toDateString(),
            'end_date' => $this->today->addDays(30)->toDateString(),
        ], $overrides);
    }

    private function demoPlan(): NutritionPlan
    {
        return NutritionPlan::where('title', 'Demo Nutrition Plan')->firstOrFail();
    }

    // ----- Plans -----

    public function test_nutritionist_can_create_a_plan(): void
    {
        Sanctum::actingAs($this->nutritionistUser);

        $this->postJson('/api/nutritionist/plans', $this->planPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cutting Plan')
            ->assertJsonPath('data.nutritionist_id', $this->nutritionistUser->nutritionist->nutritionist_id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('nutrition_plans', [
            'athlete_id' => $this->otherUser->athlete->athlete_id,
            'title' => 'Cutting Plan',
        ]);
    }

    public function test_athlete_cannot_manage_plans(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->postJson('/api/nutritionist/plans', $this->planPayload())->assertForbidden();
        $this->getJson('/api/nutritionist/plans')->assertForbidden();
        $this->getJson('/api/nutritionist/athletes')->assertForbidden();
    }

    public function test_plan_validation_errors(): void
    {
        Sanctum::actingAs($this->nutritionistUser);

        $this->postJson('/api/nutritionist/plans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'athlete_id', 'title', 'daily_calories_target', 'start_date', 'end_date',
            ]);

        $this->postJson('/api/nutritionist/plans', $this->planPayload([
            'end_date' => $this->today->subDay()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');

        $this->postJson('/api/nutritionist/plans', $this->planPayload([
            'athlete_id' => 99999,
        ]))->assertUnprocessable()->assertJsonValidationErrors('athlete_id');

        $this->postJson('/api/nutritionist/plans', $this->planPayload([
            'nutritionist_id' => 5,
        ]))->assertUnprocessable()->assertJsonValidationErrors('nutritionist_id');
    }

    public function test_overlapping_active_plan_is_rejected(): void
    {
        Sanctum::actingAs($this->nutritionistUser);

        // The demo athlete already has an active plan covering today.
        $this->postJson('/api/nutritionist/plans', $this->planPayload([
            'athlete_id' => $this->athleteUser->athlete->athlete_id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('start_date');

        // An inactive plan may overlap.
        $this->postJson('/api/nutritionist/plans', $this->planPayload([
            'athlete_id' => $this->athleteUser->athlete->athlete_id,
            'is_active' => false,
        ]))->assertCreated();
    }

    public function test_partial_update_of_a_plan(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $plan = $this->demoPlan();

        $response = $this->patchJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}", [
            'daily_calories_target' => 2200,
        ])->assertOk()->assertJsonPath('data.title', 'Demo Nutrition Plan');

        $this->assertEquals(2200, $response->json('data.daily_calories_target'));
    }

    public function test_update_cannot_change_athlete_or_break_date_range(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $plan = $this->demoPlan();

        $this->patchJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}", [
            'athlete_id' => $this->otherUser->athlete->athlete_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('athlete_id');

        $this->patchJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}", [
            'end_date' => $this->today->subDays(30)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_plans_list_and_show_include_only_own_plans(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $plan = $this->demoPlan();

        $this->getJson('/api/nutritionist/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Demo Nutrition Plan')
            ->assertJsonCount(2, 'data.meals');
    }

    public function test_nutritionist_cannot_touch_another_nutritionists_plan(): void
    {
        $stranger = User::factory()->create(['email' => 'stranger@example.test']);
        $stranger->forceFill(['role' => 'nutritionist', 'is_active' => true])->save();
        $stranger->nutritionist()->create();

        $plan = $this->demoPlan();
        $meal = Meal::firstOrFail();

        Sanctum::actingAs($stranger);

        $this->getJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}")->assertNotFound();
        $this->patchJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}", ['title' => 'x'])->assertNotFound();
        $this->deleteJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}")->assertNotFound();
        $this->patchJson("/api/nutritionist/meals/{$meal->meal_id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/nutritionist/meals/{$meal->meal_id}")->assertNotFound();
        $this->postJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}/meals", [
            'name' => 'x', 'meal_type' => 'lunch', 'calories' => 1,
        ])->assertNotFound();
    }

    public function test_deleting_a_plan_removes_its_meals_but_keeps_logs(): void
    {
        $plan = $this->demoPlan();
        $meal = $plan->meals()->first();

        $log = $this->athleteUser->athlete->mealLogs()->create([
            'meal_id' => $meal->meal_id,
            'name' => 'Linked log',
            'meal_type' => 'breakfast',
            'calories' => 100,
            'consumed_at' => $this->today->utc(),
        ]);

        Sanctum::actingAs($this->nutritionistUser);

        $this->deleteJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}")->assertNoContent();

        $this->assertDatabaseMissing('nutrition_plans', ['nutrition_plan_id' => $plan->nutrition_plan_id]);
        $this->assertDatabaseCount('meals', 0);
        $this->assertNull(MealLog::findOrFail($log->meal_log_id)->meal_id);
    }

    // ----- Meals -----

    public function test_nutritionist_can_add_update_and_delete_a_meal(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $plan = $this->demoPlan();

        $created = $this->postJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}/meals", [
            'name' => 'Dinner Salad',
            'meal_type' => 'dinner',
            'calories' => 350.5,
            'description' => 'Light dinner',
        ])->assertCreated()->assertJsonPath('data.name', 'Dinner Salad');

        $mealId = $created->json('data.meal_id');
        $this->assertSame($plan->nutrition_plan_id, $created->json('data.nutrition_plan_id'));

        $this->patchJson("/api/nutritionist/meals/{$mealId}", ['calories' => 400])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dinner Salad');

        $this->deleteJson("/api/nutritionist/meals/{$mealId}")->assertNoContent();
        $this->assertDatabaseMissing('meals', ['meal_id' => $mealId]);
    }

    public function test_meal_validation_errors(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $plan = $this->demoPlan();

        $this->postJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}/meals", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'meal_type', 'calories']);

        $this->postJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}/meals", [
            'name' => 'x', 'meal_type' => 'brunch', 'calories' => -1,
            'nutrition_plan_id' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['meal_type', 'calories', 'nutrition_plan_id']);
    }

    // ----- Athletes list -----

    public function test_nutritionist_sees_athletes_of_own_plans(): void
    {
        Sanctum::actingAs($this->nutritionistUser);

        $response = $this->getJson('/api/nutritionist/athletes')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('athlete@example.test', $response->json('data.0.email'));
    }

    public function test_planned_meals_are_visible_to_the_athlete_after_nutritionist_adds_them(): void
    {
        $plan = $this->demoPlan();

        Sanctum::actingAs($this->nutritionistUser);
        $this->postJson("/api/nutritionist/plans/{$plan->nutrition_plan_id}/meals", [
            'name' => 'Extra Snack', 'meal_type' => 'snack', 'calories' => 150,
        ])->assertCreated();

        Sanctum::actingAs($this->athleteUser);
        $response = $this->getJson('/api/planned-meals?date='.$this->today->toDateString())->assertOk();

        $this->assertCount(3, $response->json('data.0.meals'));
    }

    // ----- Unified 403 / pagination -----

    public function test_nonexistent_and_unrelated_athlete_give_the_same_403(): void
    {
        Sanctum::actingAs($this->nutritionistUser);
        $date = $this->today->toDateString();

        $this->getJson("/api/nutritionist/athletes/99999/meal-logs?date={$date}")->assertForbidden();
        $this->getJson("/api/nutritionist/athletes/{$this->otherUser->athlete->athlete_id}/meal-logs?date={$date}")
            ->assertForbidden();
    }

    public function test_meal_logs_are_paginated_and_numeric(): void
    {
        MealLog::query()->delete(); // drop the seeded demo logs

        foreach (range(1, 3) as $i) {
            $this->athleteUser->athlete->mealLogs()->create([
                'name' => "Meal {$i}",
                'meal_type' => 'snack',
                'calories' => 100.5,
                'consumed_at' => $this->today->addHours($i)->utc(),
            ]);
        }

        Sanctum::actingAs($this->athleteUser);

        $response = $this->getJson('/api/meal-logs?per_page=2&date='.$this->today->toDateString())
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta', 'date', 'timezone']);

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(100.5, $response->json('data.0.calories'));

        $this->getJson('/api/meal-logs?per_page=500&date='.$this->today->toDateString())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
