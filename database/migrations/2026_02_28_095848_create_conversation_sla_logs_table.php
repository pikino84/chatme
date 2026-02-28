<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_sla_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('metric', ['first_response', 'resolution'])->default('first_response');
            $table->unsignedInteger('target_seconds');
            $table->unsignedInteger('actual_seconds')->nullable();
            $table->boolean('breached')->default(false);
            $table->timestamp('breached_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('conversation_id');
            $table->index('metric');
            $table->index('breached');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_sla_logs');
    }
};
