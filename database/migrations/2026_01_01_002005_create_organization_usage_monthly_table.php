<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_usage_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('feature_code');
            $table->string('period', 7); // 2026-03
            $table->unsignedInteger('usage')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'feature_code', 'period']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_usage_monthly');
    }
};
