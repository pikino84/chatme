<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE channels ALTER COLUMN configuration TYPE text USING configuration::text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE channels ALTER COLUMN configuration TYPE jsonb USING configuration::jsonb');
    }
};
