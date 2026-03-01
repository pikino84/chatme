<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->timestamp('stage_entered_at')->nullable();
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('pipeline_id');
            $table->index('pipeline_stage_id');
            $table->index('conversation_id');
            $table->index('assigned_user_id');
            $table->index(['organization_id', 'status']);
            $table->index('stage_entered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
