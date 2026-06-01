<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
        });

        // Backfill: cada usuario queda vinculado a su organización actual.
        // is_owner = true para los org_admin (dueños), false para el resto.
        $ownerIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'org_admin')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->pluck('model_has_roles.model_id')
            ->flip();

        $now = now();

        DB::table('users')->whereNotNull('organization_id')->orderBy('id')
            ->select('id', 'organization_id')
            ->chunk(200, function ($users) use ($ownerIds, $now) {
                $rows = [];
                foreach ($users as $u) {
                    $rows[] = [
                        'user_id' => $u->id,
                        'organization_id' => $u->organization_id,
                        'is_owner' => isset($ownerIds[$u->id]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    DB::table('organization_user')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
