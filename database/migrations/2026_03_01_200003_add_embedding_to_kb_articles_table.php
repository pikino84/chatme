<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            // pgvector extension not available — skip silently
            // Embeddings will be null until pgvector is installed
            return;
        }

        try {
            DB::statement('ALTER TABLE kb_articles ADD COLUMN embedding vector(1536)');
        } catch (\Throwable $e) {
            // Column may already exist or vector type not available
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE kb_articles DROP COLUMN IF EXISTS embedding');
        } catch (\Throwable $e) {
            // Ignore if column doesn't exist
        }
    }
};
