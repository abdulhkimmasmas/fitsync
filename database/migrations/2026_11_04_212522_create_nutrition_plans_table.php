<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->bigIncrements('nutrition_plan_id');

            $table->unsignedBigInteger('athlete_id');
            $table->unsignedBigInteger('nutritionist_id');

            $table->string('title', 150);
            $table->decimal('daily_calories_target', 8, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->restrictOnDelete();

            $table->foreign('nutritionist_id')
                ->references('nutritionist_id')
                ->on('nutritionists')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_plans');
    }
};