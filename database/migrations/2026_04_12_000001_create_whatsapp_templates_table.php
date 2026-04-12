<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language', 10)->default('es_MX');
            $table->string('category'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED, PAUSED, DISABLED
            $table->json('components')->nullable();
            $table->string('header_type')->nullable(); // none, text, image, video, document
            $table->string('header_media_path')->nullable();
            $table->text('body_text');
            $table->json('body_example_values')->nullable();
            $table->string('footer_text')->nullable();
            $table->json('buttons')->nullable();
            $table->string('meta_template_id')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'language']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'channel_id']);
        });

        // Add 'template' to messages type constraint
        DB::statement("ALTER TABLE messages DROP CONSTRAINT messages_type_check");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text','image','video','file','audio','internal_note','template']::text[]))");
    }

    public function down(): void
    {
        // Restore messages type constraint without 'template'
        DB::statement("ALTER TABLE messages DROP CONSTRAINT messages_type_check");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text','image','video','file','audio','internal_note']::text[]))");

        Schema::dropIfExists('whatsapp_templates');
    }
};
