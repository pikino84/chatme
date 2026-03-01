<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('content');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary')->nullable();
            $table->timestamps();

            $table->index(['kb_article_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_versions');
    }
};
