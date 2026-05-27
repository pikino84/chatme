<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 22 — migración de bridge Baileys custom a Evolution API.
 *
 * Cada channel 'whatsapp_web' pasa a corresponder a una "instancia" de Evolution.
 * Guardamos el nombre, el id y la apikey de esa instancia en la sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_web_sessions', function (Blueprint $table) {
            $table->string('instance_name')->nullable()->after('channel_id');
            $table->string('instance_id')->nullable()->after('instance_name');
            $table->string('instance_apikey')->nullable()->after('instance_id');
            $table->index('instance_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_web_sessions', function (Blueprint $table) {
            $table->dropIndex(['instance_name']);
            $table->dropColumn(['instance_name', 'instance_id', 'instance_apikey']);
        });
    }
};
