<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el CHECK constraint de channels.type para aceptar 'whatsapp_web'
        // Laravel traduce enum() a VARCHAR + CHECK en PostgreSQL.
        DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
        DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('whatsapp', 'webchat', 'email', 'facebook', 'instagram', 'whatsapp_web'))");

        // 2. Tabla de sesiones vivas del bridge Baileys
        Schema::create('whatsapp_web_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('disconnected');
            // disconnected | qr_pending | connecting | connected | logged_out | error
            $table->text('qr_raw')->nullable();
            $table->string('connected_phone')->nullable();
            $table->string('connected_name')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_disconnected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('channel_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_web_sessions');

        DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
        DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('whatsapp', 'webchat', 'email', 'facebook', 'instagram'))");
    }
};
