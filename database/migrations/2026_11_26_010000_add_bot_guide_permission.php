<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('access_roles')->where('is_admin', false)->get()->each(function ($role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            $permissions[] = 'bot_guide';
            DB::table('access_roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('access_roles')->where('is_admin', false)->get()->each(function ($role) {
            $permissions = array_values(array_filter(
                json_decode($role->permissions, true) ?: [],
                fn ($permission) => $permission !== 'bot_guide'
            ));
            DB::table('access_roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        });
    }
};
