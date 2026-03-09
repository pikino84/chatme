<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('color', 7)->default('#4f46e5');
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('kb_categories', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        $tables = ['kb_categories', 'kb_articles', 'conversations', 'channels', 'branches'];

        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('brand_id');
            });
        }

        Schema::dropIfExists('brands');
    }
};
