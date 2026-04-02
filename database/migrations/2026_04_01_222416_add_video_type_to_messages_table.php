<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE messages DROP CONSTRAINT messages_type_check");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text','image','video','file','audio','internal_note']::text[]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE messages DROP CONSTRAINT messages_type_check");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text','image','file','audio','internal_note']::text[]))");
    }
};
