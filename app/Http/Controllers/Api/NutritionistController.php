<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMealRequest;
use App\Http\Requests\SaveNutritionPlanRequest;
use App\Http\Resources\MealResource;
use App\Http\Resources\NutritionPlanResource;
use App\Models\Meal;
use App\Models\Nutritionist;
use App\Models\NutritionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NutritionistController extends Controller
{
    private function nutritionist(Request $request): Nutritionist
    {
        abort_unless(
            $request->user()->is_active &&
            $request->user()->role === 'nutritionist',
            403
        );

        $nutritionist = $request->user()->nutritionist;

        abort_unless($nutritionist, 403);

        return $nutritionist;
    }

    /**
     * A plan that belongs to another nutritionist is reported as 404.
     */
    private function ownPlan(Nutritionist $nutritionist, string $id): NutritionPlan
    {
        return $nutritionist->nutritionPlans()
            ->where('nutrition_plan_id', $id)
            ->firstOrFail();
    }

    private function ownMeal(Nutritionist $nutritionist, string $id): Meal
    {
        return Meal::query()
            ->where('meal_id', $id)
            ->whereIn(
                'nutrition_plan_id',
                $nutritionist->nutritionPlans()->select('nutrition_plan_id')
            )
            ->firstOrFail();
    }

    /**
     * The daily summary needs exactly one active target per day, so two
     * active plans of the same athlete must never overlap.
     */
    private function assertNoOverlap(
        int $athleteId,
        string $start,
        string $end,
        ?int $ignorePlanId = null
    ): void {
        $overlaps = NutritionPlan::query()
            ->where('athlete_id', $athleteId)
            ->where('is_active', true)
            ->when(
                $ignorePlanId,
                fn ($q) => $q->where('nutrition_plan_id', '!=', $ignorePlanId)
            )
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'توجد خطة فعّالة أخرى لهذا اللاعب تتداخل مع هذه الفترة.',
            ]);
        }
    }

    // ----- Athletes -----

    public function athletes(Request $request)
    {
        $nutritionist = $this->nutritionist($request);

        $athletes = $nutritionist->nutritionPlans()
            ->with('athlete.user:id,name,email')
            ->get()
            ->pluck('athlete')
            ->unique('athlete_id')
            ->values()
            ->map(fn ($athlete) => [
                'athlete_id' => $athlete->athlete_id,
                'name' => $athlete->user->name,
                'email' => $athlete->user->email,
            ]);

        return response()->json(['data' => $athletes]);
    }

    // ----- Plans -----

    public function plansIndex(Request $request)
    {
        $nutritionist = $this->nutritionist($request);

        $request->validate([
            'athlete_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $plans = $nutritionist->nutritionPlans()
            ->when(
                $request->query('athlete_id'),
                fn ($q, $id) => $q->where('athlete_id', $id)
            )
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return NutritionPlanResource::collection($plans);
    }

    public function plansStore(SaveNutritionPlanRequest $request)
    {
        $nutritionist = $this->nutritionist($request);
        $data = $request->validated();

        if ($data['is_active'] ?? true) {
            $this->assertNoOverlap(
                (int) $data['athlete_id'],
                $data['start_date'],
                $data['end_date']
            );
        }

        $plan = $nutritionist->nutritionPlans()->create($data);

        return (new NutritionPlanResource($plan->fresh()))
            ->additional(['message' => 'تم إنشاء الخطة.'])
            ->response()
            ->setStatusCode(201);
    }

    public function plansShow(Request $request, string $id)
    {
        $plan = $this->ownPlan($this->nutritionist($request), $id);

        return new NutritionPlanResource($plan->load('meals'));
    }

    public function plansUpdate(SaveNutritionPlanRequest $request, string $id)
    {
        $plan = $this->ownPlan($this->nutritionist($request), $id);
        $data = $request->validated();

        $start = $data['start_date'] ?? $plan->start_date->toDateString();
        $end = $data['end_date'] ?? $plan->end_date->toDateString();

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end_date' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
            ]);
        }

        if ($data['is_active'] ?? $plan->is_active) {
            $this->assertNoOverlap(
                $plan->athlete_id,
                $start,
                $end,
                $plan->nutrition_plan_id
            );
        }

        $plan->update($data);

        return (new NutritionPlanResource($plan->fresh()->load('meals')))
            ->additional(['message' => 'تم تعديل الخطة.']);
    }

    public function plansDestroy(Request $request, string $id)
    {
        $plan = $this->ownPlan($this->nutritionist($request), $id);

        // meals.nutrition_plan_id is restrictOnDelete; logs that point to these
        // meals are kept (meal_logs.meal_id is nullOnDelete).
        DB::transaction(function () use ($plan) {
            $plan->meals()->delete();
            $plan->delete();
        });

        return response()->noContent();
    }

    // ----- Meals of a plan -----

    public function mealsStore(SaveMealRequest $request, string $planId)
    {
        $plan = $this->ownPlan($this->nutritionist($request), $planId);

        $meal = $plan->meals()->create($request->validated());

        return (new MealResource($meal->fresh()))
            ->additional(['message' => 'تمت إضافة الوجبة إلى الخطة.'])
            ->response()
            ->setStatusCode(201);
    }

    public function mealsUpdate(SaveMealRequest $request, string $id)
    {
        $meal = $this->ownMeal($this->nutritionist($request), $id);

        $meal->update($request->validated());

        return (new MealResource($meal->fresh()))
            ->additional(['message' => 'تم تعديل الوجبة.']);
    }

    public function mealsDestroy(Request $request, string $id)
    {
        $meal = $this->ownMeal($this->nutritionist($request), $id);

        $meal->delete();

        return response()->noContent();
    }
}
