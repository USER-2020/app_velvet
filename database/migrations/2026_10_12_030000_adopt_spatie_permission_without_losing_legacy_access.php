<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'guard_name')) {
                $table->string('guard_name')->default('web')->index();
            }
        });
        Schema::table('permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('permissions', 'guard_name')) {
                $table->string('guard_name')->default('web')->index();
            }
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        DB::table('role_permission')->orderBy('role_id')->orderBy('permission_id')->chunk(500, function ($rows): void {
            DB::table('role_has_permissions')->insertOrIgnore(collect($rows)->map(fn ($row) => ['permission_id' => $row->permission_id, 'role_id' => $row->role_id])->all());
        });
        DB::table('user_role')->orderBy('user_id')->orderBy('role_id')->chunk(500, function ($rows): void {
            DB::table('model_has_roles')->insertOrIgnore(collect($rows)->map(fn ($row) => ['role_id' => $row->role_id, 'model_id' => $row->user_id, 'model_type' => User::class])->all());
        });
    }

    /** Legacy tables and copied pivots are retained so adoption is non-destructive. */
    public function down(): void
    {
        // Intentionally no-op: rolling back must not remove access-control data.
    }
};
