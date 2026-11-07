<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('meal_logs', function (Blueprint $table) {
        $table->bigIncrements('meal_log_id');

        $table->unsignedBigInteger('athlete_id');
        $table->unsignedBigInteger('meal_id')->nullable();

        $table->string('name', 150);
        $table->string('meal_type', 20);
        $table->decimal('calories', 8, 2);
        $table->dateTime('consumed_at');
        $table->text('notes')->nullable();

        $table->timestamps();

        $table->foreign('athlete_id')
            ->references('athlete_id')
            ->on('athletes')
            ->restrictOnDelete();

        $table->foreign('meal_id')
            ->references('meal_id')
            ->on('meals')
            ->nullOnDelete();

        $table->index(['athlete_id', 'consumed_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('meal_logs');
}
};
