<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->unsignedInteger('max_duration_hours')->nullable();
            $table->timestamps();

            $table->index('pipeline_id');
            $table->index(['pipeline_id', 'position']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
