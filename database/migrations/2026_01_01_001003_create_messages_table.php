<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->enum('type', ['text', 'image', 'file', 'audio', 'internal_note'])->default('text');
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('external_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('conversation_id');
            $table->index('direction');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
