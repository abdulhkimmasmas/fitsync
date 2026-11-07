<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('athletes', function (Blueprint $table) {
        $table->bigIncrements('athlete_id');

        $table->foreignId('user_id')
            ->unique()
            ->constrained('users')
            ->restrictOnDelete();

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('athletes');
}
};
