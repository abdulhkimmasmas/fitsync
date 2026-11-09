<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MealDayRequest;
use App\Http\Requests\SaveMealLogRequest;
use App\Http\Resources\MealLogResource;
use App\Http\Resources\NutritionPlanResource;
use App\Models\Athlete;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class MealLogController extends Controller
{
    private function athlete(Request $request): Athlete
    {
        abort_unless(
            $request->user()->is_active &&
            $request->user()->role === 'athlete',
            403
        );

        $athlete = $request->user()->athlete;

        abort_unless($athlete, 403);

        return $athlete;
    }

    private function dayBounds(string $date): array
    {
        $start = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            config('meal_tracking.timezone')
        );

        return [
            $start->utc(),
            $start->addDay()->utc(),
        ];
    }

    private function dayQuery(Athlete $athlete, string $date)
    {
        [$start, $end] = $this->dayBounds($date);

        return $athlete->mealLogs()
            ->where('consumed_at', '>=', $start)
            ->where('consumed_at', '<', $end);
    }

    public function index(MealDayRequest $request)
    {
        $athlete = $this->athlete($request);
        $date = $request->validated('date');

        return $this->paginatedDay($request, $this->dayQuery($athlete, $date), [
            'date' => $date,
            'timezone' => config('meal_tracking.timezone'),
        ]);
    }

    private function paginatedDay(MealDayRequest $request, $query, array $extra)
    {
        $logs = $query
            ->orderBy('consumed_at')
            ->orderBy('meal_log_id')
            ->paginate($request->validated('per_page', 15))
            ->withQueryString();

        return MealLogResource::collection($logs)->additional($extra);
    }

    public function store(SaveMealLogRequest $request)
    {
        $athlete = $this->athlete($request);
        $data = $request->validated();

        $data['consumed_at'] = CarbonImmutable::parse(
            $data['consumed_at']
        )->utc();

        $log = $athlete->mealLogs()->create($data);

        return (new MealLogResource($log->fresh()))
            ->additional(['message' => 'تم تسجيل الوجبة.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveMealLogRequest $request, string $id)
    {
        $athlete = $this->athlete($request);

        $log = $athlete->mealLogs()
            ->where('meal_log_id', $id)
            ->firstOrFail();

        $data = $request->validated();

        if (array_key_exists('consumed_at', $data)) {
            $data['consumed_at'] = CarbonImmutable::parse(
                $data['consumed_at']
            )->utc();
        }

        $log->update($data);

        return (new MealLogResource($log->fresh()))
            ->additional(['message' => 'تم تعديل السجل.']);
    }

    public function destroy(Request $request, string $id)
    {
        $athlete = $this->athlete($request);

        $log = $athlete->mealLogs()
            ->where('meal_log_id', $id)
            ->firstOrFail();

        $log->delete();

        return response()->noContent();
    }

    public function summary(MealDayRequest $request)
    {
        $athlete = $this->athlete($request);
        $date = $request->validated('date');

        $totals = $this->dayQuery($athlete, $date)
            ->selectRaw(
                'COUNT(*) AS meals_count,
                 COALESCE(SUM(calories), 0) AS total_calories'
            )
            ->first();

        $plans = $athlete->nutritionPlans()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        if ($plans->count() > 1) {
            return response()->json([
                'message' => 'توجد خطط متداخلة؛ يجب تحديد هدف يومي واحد.',
            ], 409);
        }

        $target = $plans->first()?->daily_calories_target;

        $target = $target === null ? null : (float) $target;
        $total = (float) $totals->total_calories;

        return response()->json([
            'date' => $date,
            'timezone' => config('meal_tracking.timezone'),
            'logged_meals_count' => (int) $totals->meals_count,
            'logged_calories' => $total,
            'target_calories' => $target,
            'remaining_calories' => $target === null
                ? null
                : round(max($target - $total, 0), 2),
            'excess_calories' => $target === null
                ? null
                : round(max($total - $target, 0), 2),
        ]);
    }

    public function plannedMeals(MealDayRequest $request)
    {
        $athlete = $this->athlete($request);
        $date = $request->validated('date');

        return NutritionPlanResource::collection(
            $athlete->nutritionPlans()
                ->with('meals')
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->get()
        );
    }

    public function nutritionistIndex(
        MealDayRequest $request,
        string $athleteId
    ) {
        abort_unless(
            $request->user()->role === 'nutritionist',
            403
        );

        $nutritionist = $request->user()->nutritionist;

        abort_unless($nutritionist, 403);

        $date = $request->validated('date');

        $today = CarbonImmutable::now(
            config('meal_tracking.timezone')
        )->toDateString();

        // Permission is checked before the athlete is loaded, so an unrelated
        // or non-existent athlete id gives the same 403 (no id enumeration).
        $allowed = $nutritionist->nutritionPlans()
            ->where('athlete_id', $athleteId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        abort_unless($allowed, 403);

        $athlete = Athlete::findOrFail($athleteId);

        return $this->paginatedDay($request, $this->dayQuery($athlete, $date), [
            'athlete_id' => $athlete->athlete_id,
            'date' => $date,
            'timezone' => config('meal_tracking.timezone'),
        ]);
    }
}