<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drip_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'paused', 'archived'])->default('paused');
            $table->string('trigger_event')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
        });

        Schema::create('drip_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drip_sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->text('message_template');
            $table->enum('channel_type', ['whatsapp', 'webchat', 'facebook', 'instagram'])->default('whatsapp');
            $table->timestamps();

            $table->index('drip_sequence_id');
            $table->index(['drip_sequence_id', 'position']);
        });

        Schema::create('drip_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drip_sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'canceled'])->default('active');
            $table->timestamp('next_step_at')->nullable();
            $table->timestamps();

            $table->index('drip_sequence_id');
            $table->index('contact_id');
            $table->index(['status', 'next_step_at']);
            $table->unique(['drip_sequence_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_enrollments');
        Schema::dropIfExists('drip_sequence_steps');
        Schema::dropIfExists('drip_sequences');
    }
};
