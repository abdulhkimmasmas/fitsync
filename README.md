# FitSync Backend — Meal Tracking API

Laravel 12 + Sanctum (Bearer tokens). Athletes log what they eat, nutritionists
build nutrition plans (with planned meals) and follow their athletes' logs.

## Setup

```bash
composer install
cp .env.example .env          # MySQL database `fitsync`, user root, no password
php artisan key:generate
php artisan migrate:fresh --seed   # local/testing only
php artisan serve
php artisan test                   # runs on sqlite in-memory, never touches MySQL
```

Demo accounts (password `DemoPassword!123`):
`athlete@example.test`, `other@example.test` (athletes), `nutritionist@example.test`.

## Endpoints (prefix `/api`, send `Accept: application/json`)

| Method | Path | Who | Purpose |
|---|---|---|---|
| POST | `/login` | all | email + password → Bearer token (8 h) |
| POST | `/logout` | auth | revoke current token |
| GET | `/planned-meals?date=` | athlete | meals of the active plan for a day |
| GET | `/meal-logs?date=&per_page=&page=` | athlete | day's logs (paginated) |
| POST | `/meal-logs` | athlete | log a meal |
| PATCH | `/meal-logs/{id}` | athlete | partial update of own log |
| DELETE | `/meal-logs/{id}` | athlete | delete own log |
| GET | `/meal-logs/summary?date=` | athlete | logged vs target calories |
| GET | `/nutritionist/athletes` | nutritionist | athletes with a plan from me |
| GET | `/nutritionist/athletes/{id}/meal-logs?date=` | nutritionist | an athlete's logs (needs active plan) |
| GET/POST | `/nutritionist/plans` | nutritionist | list / create plans |
| GET/PATCH/DELETE | `/nutritionist/plans/{id}` | nutritionist | show / update / delete own plan |
| POST | `/nutritionist/plans/{planId}/meals` | nutritionist | add a planned meal |
| PATCH/DELETE | `/nutritionist/meals/{id}` | nutritionist | update / delete a planned meal |

`consumed_at` format: `Y-m-d\TH:i:sP`, e.g. `2026-11-07T12:30:00+03:00`, not in the future.
Days are cut at midnight in `Asia/Damascus` (`config/meal_tracking.php`).

A ready-made Postman collection is in `postman/`.
