<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_cannot_fetch_secrets(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->postJson('/akun/1/salin')->assertUnauthorized();
    }

    public function test_account_lifecycle_and_encrypted_storage(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/akun', ['label'=>'QA', 'username'=>'user@example.test', 'password'=>'demo-secret'])->assertRedirect('/akun');
        $record = DB::table('office_accounts')->first();
        $this->assertNotSame('demo-secret', $record->password);
        $this->assertNotSame('user@example.test', $record->username);
        $this->get('/akun')->assertOk()->assertSee('QA')->assertDontSee('demo-secret');
        $this->put('/akun/'.$record->id, ['label'=>'Baru', 'username'=>'new@example.test'])->assertRedirect('/akun');
        $this->postJson('/akun/'.$record->id.'/salin')->assertJsonPath('value','demo-secret');
        $this->delete('/akun/'.$record->id)->assertRedirect('/akun');
        $this->assertDatabaseCount('office_accounts', 0);
    }

    public function test_daily_report_and_password_change(): void
    {
        $user = User::factory()->create(['password'=>'password-awal']);
        $this->actingAs($user);
        $this->post('/laporan', ['author'=>'Teknisi', 'body'=>'Cek jaringan', 'report_date'=>'2026-11-23'])->assertRedirect('/laporan');
        $this->get('/laporan')->assertOk()->assertSee('Teknisi')->assertDontSee('Cek jaringan');
        $this->get('/laporan?author=Teknisi')->assertOk()->assertSee('Cek jaringan');
        $this->put('/pengaturan', ['current_password'=>'password-awal','password'=>'password-baru-123','password_confirmation'=>'password-baru-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
        $this->post('/keluar')->assertRedirect('/login');
        $this->assertGuest();
    }
    public function test_employee_monthly_report_crud(): void
    {
        $this->actingAs(User::factory()->create());
        $employee=DB::table('employees')->insertGetId(['name'=>'Pegawai A','position'=>'Teknisi','phone'=>'628999000001']);
        $url='/laporan?employee='.$employee.'&month=2026-11';
        $data=['employee_id'=>$employee,'report_date'=>'2026-11-23','body'=>'Cek inventaris','detail'=>1,'month'=>'2026-11'];
        $this->post('/laporan',$data)->assertRedirect($url);
        $id=DB::table('daily_reports')->value('id');
        $this->get($url)->assertOk()->assertSee('Cek inventaris');
        $this->get('/laporan?employee='.$employee.'&month=2026-10')->assertDontSee('Cek inventaris');
        $this->put('/laporan/'.$id,array_replace($data,['body'=>'Inventaris selesai']))->assertRedirect($url);
        $this->get($url)->assertSee('Inventaris selesai');
        $this->delete('/laporan/'.$id)->assertRedirect();
        $this->assertDatabaseCount('daily_reports',0);
    }
}
