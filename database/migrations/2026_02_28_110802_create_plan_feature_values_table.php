<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_feature_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_feature_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();

            $table->unique(['plan_id', 'plan_feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_feature_values');
    }
};
