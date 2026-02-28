<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->string('subject')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_identifier');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
            $table->index('assigned_user_id');
            $table->index('channel_id');
            $table->index(['organization_id', 'status']);
            $table->index('contact_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
