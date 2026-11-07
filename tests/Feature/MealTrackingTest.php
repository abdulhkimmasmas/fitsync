<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\MealLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MealTrackingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MealTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'DemoPassword!123';

    private User $athleteUser;

    private User $otherUser;

    private User $nutritionistUser;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MealTrackingSeeder::class);

        // Start every test with an empty log table.
        MealLog::query()->delete();

        $this->athleteUser = User::where('email', 'athlete@example.test')->firstOrFail();
        $this->otherUser = User::where('email', 'other@example.test')->firstOrFail();
        $this->nutritionistUser = User::where('email', 'nutritionist@example.test')->firstOrFail();

        $this->today = $this->now()->toDateString();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('meal_tracking.timezone'));
    }

    private function startOfToday(): string
    {
        return $this->now()->startOfDay()->format('Y-m-d\TH:i:sP');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Grilled Chicken',
            'meal_type' => 'lunch',
            'calories' => 450,
            'consumed_at' => $this->startOfToday(),
            'notes' => 'test',
        ], $overrides);
    }

    private function createLogFor(User $user, array $overrides = []): MealLog
    {
        return $user->athlete->mealLogs()->create(array_merge([
            'name' => 'Existing Meal',
            'meal_type' => 'snack',
            'calories' => 200,
            'consumed_at' => $this->now()->startOfDay()->utc(),
        ], $overrides));
    }

    // ----- Auth -----

    public function test_login_returns_token(): void
    {
        $this->postJson('/api/login', [
            'email' => 'athlete@example.test',
            'password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonStructure(['token_type', 'token', 'user' => ['id', 'name', 'role']])
            ->assertJsonPath('user.role', 'athlete');
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->postJson('/api/login', [
            'email' => 'athlete@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->athleteUser->forceFill(['is_active' => false])->save();

        $this->postJson('/api/login', [
            'email' => 'athlete@example.test',
            'password' => self::PASSWORD,
        ])->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->athleteUser->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_requests_without_token_get_json_401(): void
    {
        $this->getJson("/api/meal-logs?date={$this->today}")->assertUnauthorized();

        // No Accept header: must still be JSON 401, not a 500 / redirect.
        $this->get("/api/meal-logs?date={$this->today}")
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    // ----- Create -----

    public function test_athlete_can_create_a_meal_log(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->postJson('/api/meal-logs', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Grilled Chicken')
            ->assertJsonPath('data.athlete_id', $this->athleteUser->athlete->athlete_id);

        $this->assertDatabaseHas('meal_logs', [
            'athlete_id' => $this->athleteUser->athlete->athlete_id,
            'name' => 'Grilled Chicken',
        ]);
    }

    public function test_athlete_id_in_body_is_rejected(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->postJson('/api/meal-logs', $this->payload([
            'athlete_id' => $this->otherUser->athlete->athlete_id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('athlete_id');
    }

    public function test_future_consumed_at_is_rejected(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->postJson('/api/meal-logs', $this->payload([
            'consumed_at' => $this->now()->addDay()->format('Y-m-d\TH:i:sP'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('consumed_at');
    }

    public function test_invalid_meal_type_and_missing_fields_are_rejected(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->postJson('/api/meal-logs', $this->payload(['meal_type' => 'brunch']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meal_type');

        $this->postJson('/api/meal-logs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'meal_type', 'calories', 'consumed_at']);
    }

    public function test_nutritionist_cannot_create_a_meal_log(): void
    {
        Sanctum::actingAs($this->nutritionistUser);

        $this->postJson('/api/meal-logs', $this->payload())->assertForbidden();
    }

    // ----- Read -----

    public function test_index_returns_only_logs_of_the_requested_day(): void
    {
        $this->createLogFor($this->athleteUser, ['name' => 'Today Meal']);
        $this->createLogFor($this->athleteUser, [
            'name' => 'Old Meal',
            'consumed_at' => $this->now()->startOfDay()->subDays(3)->utc(),
        ]);

        Sanctum::actingAs($this->athleteUser);

        $response = $this->getJson("/api/meal-logs?date={$this->today}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Today Meal', $response->json('data.0.name'));
    }

    public function test_index_requires_a_valid_date(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $this->getJson('/api/meal-logs')->assertUnprocessable();
        $this->getJson('/api/meal-logs?date=07-11-2026')->assertUnprocessable();
    }

    public function test_athlete_does_not_see_other_athletes_logs(): void
    {
        $this->createLogFor($this->otherUser, ['name' => 'Secret Meal']);

        Sanctum::actingAs($this->athleteUser);

        $this->getJson("/api/meal-logs?date={$this->today}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ----- Update / Delete -----

    public function test_partial_update_changes_only_sent_fields(): void
    {
        $log = $this->createLogFor($this->athleteUser, ['name' => 'Keep Me', 'calories' => 200]);

        Sanctum::actingAs($this->athleteUser);

        $response = $this->patchJson("/api/meal-logs/{$log->meal_log_id}", ['calories' => 500])
            ->assertOk()
            ->assertJsonPath('data.name', 'Keep Me');

        $this->assertEquals(500, $response->json('data.calories'));
    }

    public function test_athlete_cannot_update_or_delete_another_athletes_log(): void
    {
        $log = $this->createLogFor($this->otherUser);

        Sanctum::actingAs($this->athleteUser);

        $this->patchJson("/api/meal-logs/{$log->meal_log_id}", ['calories' => 1])->assertNotFound();
        $this->deleteJson("/api/meal-logs/{$log->meal_log_id}")->assertNotFound();

        $this->assertDatabaseHas('meal_logs', ['meal_log_id' => $log->meal_log_id]);
    }

    public function test_athlete_can_delete_own_log(): void
    {
        $log = $this->createLogFor($this->athleteUser);

        Sanctum::actingAs($this->athleteUser);

        $this->deleteJson("/api/meal-logs/{$log->meal_log_id}")->assertNoContent();

        $this->assertDatabaseMissing('meal_logs', ['meal_log_id' => $log->meal_log_id]);
    }

    // ----- Summary / planned meals -----

    public function test_summary_compares_logged_calories_with_plan_target(): void
    {
        $this->createLogFor($this->athleteUser, ['calories' => 450]);
        $this->createLogFor($this->athleteUser, ['name' => 'Second', 'calories' => 300]);

        Sanctum::actingAs($this->athleteUser);

        $this->getJson("/api/meal-logs/summary?date={$this->today}")
            ->assertOk()
            ->assertJsonPath('logged_meals_count', 2)
            ->assertJsonPath('logged_calories', 750)
            ->assertJsonPath('target_calories', 2000)
            ->assertJsonPath('remaining_calories', 1250)
            ->assertJsonPath('excess_calories', 0);
    }

    public function test_summary_reports_excess_when_over_target(): void
    {
        $this->createLogFor($this->athleteUser, ['calories' => 2500]);

        Sanctum::actingAs($this->athleteUser);

        $this->getJson("/api/meal-logs/summary?date={$this->today}")
            ->assertOk()
            ->assertJsonPath('remaining_calories', 0)
            ->assertJsonPath('excess_calories', 500);
    }

    public function test_planned_meals_returns_active_plan_with_meals(): void
    {
        Sanctum::actingAs($this->athleteUser);

        $response = $this->getJson("/api/planned-meals?date={$this->today}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertNotEmpty($response->json('data.0.meals'));
    }

    // ----- Nutritionist -----

    public function test_nutritionist_can_view_logs_of_an_athlete_with_active_plan(): void
    {
        $this->createLogFor($this->athleteUser, ['name' => 'Visible']);
        $athleteId = $this->athleteUser->athlete->athlete_id;

        Sanctum::actingAs($this->nutritionistUser);

        $response = $this->getJson("/api/nutritionist/athletes/{$athleteId}/meal-logs?date={$this->today}")
            ->assertOk();

        $this->assertSame('Visible', $response->json('data.0.name'));
    }

    public function test_nutritionist_cannot_view_unrelated_athlete(): void
    {
        $athleteId = $this->otherUser->athlete->athlete_id;

        Sanctum::actingAs($this->nutritionistUser);

        $this->getJson("/api/nutritionist/athletes/{$athleteId}/meal-logs?date={$this->today}")
            ->assertForbidden();
    }

    public function test_athlete_cannot_use_nutritionist_endpoint(): void
    {
        $athleteId = Athlete::firstOrFail()->athlete_id;

        Sanctum::actingAs($this->athleteUser);

        $this->getJson("/api/nutritionist/athletes/{$athleteId}/meal-logs?date={$this->today}")
            ->assertForbidden();
    }
}
