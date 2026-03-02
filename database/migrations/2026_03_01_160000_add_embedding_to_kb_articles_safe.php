<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array('embedding', Schema::getColumnListing('kb_articles'))) {
            return;
        }

        try {
            DB::statement('SAVEPOINT add_embedding');
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE kb_articles ADD COLUMN embedding vector(1536)');
            DB::statement('RELEASE SAVEPOINT add_embedding');
        } catch (\Throwable $e) {
            try {
                DB::statement('ROLLBACK TO SAVEPOINT add_embedding');
            } catch (\Throwable) {
                // Savepoint may not exist
            }
        }
    }

    public function down(): void
    {
        if (in_array('embedding', Schema::getColumnListing('kb_articles'))) {
            DB::statement('ALTER TABLE kb_articles DROP COLUMN embedding');
        }
    }
};
