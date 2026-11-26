<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('permissions');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        $admin = DB::table('access_roles')->insertGetId([
            'name' => 'Admin IT',
            'permissions' => json_encode(['accounts','reports','report_bot','zoom','pins','access']),
            'is_admin' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_roles')->insert([
            'name' => 'Pegawai',
            'permissions' => json_encode(['reports']),
            'is_admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('access_role_id')->nullable()->constrained('access_roles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->unique()->constrained('employees')->nullOnDelete();
            $table->boolean('active')->default(true);
        });
        DB::table('users')->update(['access_role_id' => $admin]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('access_role_id');
            $table->dropColumn('active');
        });
        Schema::dropIfExists('access_roles');
    }
};
