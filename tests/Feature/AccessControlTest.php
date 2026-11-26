<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_employee_account_and_permissions_apply_to_routes(): void
    {
        $admin = User::factory()->create();
        $employeeId = DB::table('employees')->insertGetId(['name'=>'Hilman','position'=>'Prakom','phone'=>'628111111111','active'=>true]);
        $this->actingAs($admin)->post('/akses/role', ['name'=>'Pelapor','permissions'=>['reports']])->assertRedirect('/akses');
        $role = AccessRole::where('name','Pelapor')->firstOrFail();
        $this->post('/akses/pengguna', [
            'name'=>'Hilman','email'=>'hilman@example.test','employee_id'=>$employeeId,
            'access_role_id'=>$role->id,'password'=>'password-uji-123','password_confirmation'=>'password-uji-123','active'=>1,
        ])->assertRedirect('/akses');
        $member = User::where('email','hilman@example.test')->firstOrFail();
        $this->assertSame($employeeId, $member->employee_id);
        $this->post('/keluar');
        $this->post('/login', ['email'=>$member->email,'password'=>'password-uji-123'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertDontSee('Buka clipboard');
        $this->get('/laporan')->assertOk();
        $this->get('/akun')->assertForbidden();
        $this->post('/akun', ['label'=>'Rahasia','username'=>'x','password'=>'y'])->assertForbidden();
        $this->get('/zoom')->assertForbidden();
        $this->get('/akses')->assertForbidden();
        $this->get('/laporan/bot')->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in_and_admin_cannot_remove_own_access(): void
    {
        $admin = User::factory()->create(['email'=>'admin@example.test','password'=>'password-uji-123']);
        $this->actingAs($admin)->put('/akses/pengguna/'.$admin->id, [
            'name'=>'Admin','email'=>$admin->email,'access_role_id'=>AccessRole::where('name','Pegawai')->value('id'),
        ])->assertSessionHasErrors('access_role_id');
        $this->delete('/akses/pengguna/'.$admin->id)->assertForbidden();
        $admin->update(['active'=>false]);
        $this->post('/keluar');
        $this->post('/login', ['email'=>$admin->email,'password'=>'password-uji-123'])->assertSessionHasErrors('email');
    }
}
