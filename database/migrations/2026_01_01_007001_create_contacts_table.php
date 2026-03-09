<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('external_id')->nullable();
            $table->string('channel_type')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('phone');
            $table->index('email');
            $table->index('external_id');
            $table->unique(['organization_id', 'phone']);
            $table->index(['organization_id', 'email']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index('contact_id');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('assigned_user_id')->constrained()->nullOnDelete();
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::dropIfExists('contacts');
    }
};
