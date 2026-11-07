<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->unsignedBigInteger('nutrition_plan_id')->nullable();
            $table->string('name', 150)->nullable();
            $table->string('meal_type', 20)->nullable();
            $table->text('description')->nullable();
            $table->decimal('calories', 8, 2)->nullable();
        });

        Schema::table('meals', function (Blueprint $table) {
            $table->foreign('nutrition_plan_id')
                ->references('nutrition_plan_id')
                ->on('nutrition_plans')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropForeign(['nutrition_plan_id']);

            $table->dropColumn([
                'nutrition_plan_id',
                'name',
                'meal_type',
                'description',
                'calories',
            ]);
        });
    }
};