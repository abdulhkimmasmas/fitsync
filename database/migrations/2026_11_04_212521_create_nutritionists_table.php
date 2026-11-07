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
    Schema::create('nutritionists', function (Blueprint $table) {
        $table->bigIncrements('nutritionist_id');

        $table->foreignId('user_id')
            ->unique()
            ->constrained('users')
            ->restrictOnDelete();

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('nutritionists');
}
};
