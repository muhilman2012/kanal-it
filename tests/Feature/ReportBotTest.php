<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportBotTest extends TestCase
{
    use RefreshDatabase;
    private array $message=['phone'=>'628220000000','group'=>null,'mentioned'=>false,'id'=>'message1','timestamp'=>1795453260,'text'=>'Cek perangkat kantor'];
    protected function setUp():void
    {
        parent::setUp();
        config(['services.report_bot.token'=>'testing']);
        DB::table('employees')->insert(['name'=>'Pegawai Uji','phone'=>$this->message['phone'],'position'=>'Teknisi']);
    }
    private function send(array $changes=[])
    {
        return $this->withToken('testing')->postJson('/api/laporan/masuk',array_replace($this->message,$changes));
    }
    public function test_halo_shows_employee_menu_and_zoom_only_for_other_numbers():void
    {
        $this->send(['text'=>'/halo'])->assertOk()
            ->assertSee('/akun')->assertSee('/pdf')->assertSee('/penting')
            ->assertSee('/zoom')->assertSee('/tutorial');
        $outside='628330000000';
        $this->send(['phone'=>$outside,'text'=>'/halo'])->assertOk()->assertSee('/zoom')
            ->assertDontSee('/akun')->assertDontSee('/pdf')->assertDontSee('/penting');
        $this->send(['phone'=>$outside,'text'=>'/akun'])->assertNoContent();
        $this->send(['phone'=>$outside,'text'=>'Laporan pekerjaan'])->assertNoContent();
        $this->send(['phone'=>$outside,'text'=>'/zoom'])->assertOk()->assertSee('Tanggal kegiatan');
        $this->send(['phone'=>$outside,'text'=>'2026-12-01'])->assertOk()->assertSee('Jam mulai');
        $this->assertDatabaseCount('daily_reports',0);
        DB::table('report_groups')->insert(['jid'=>'123@g.us']);
        $this->send(['phone'=>$outside,'group'=>'123@g.us','text'=>'/halo'])->assertOk()
            ->assertSee('/zoom')->assertDontSee('/akun');
        $this->send(['phone'=>$outside,'group'=>'456@g.us','text'=>'/halo'])->assertNoContent();
        $this->send(['group'=>'123@g.us','text'=>'/halo'])->assertOk()->assertSee('/akun');
    }
    public function test_monthly_report_lists_weekdays_and_worked_weekends_in_date_order():void
    {
        $employeeId=DB::table('employees')->where('phone',$this->message['phone'])->value('id');
        $otherId=DB::table('employees')->insertGetId(['name'=>'Pegawai Lain','phone'=>'628440000000','position'=>'Teknisi']);
        foreach ([
            ['employee_id'=>$employeeId,'author'=>'Pegawai Uji','report_date'=>'2026-11-02','attendance'=>'Masuk','body'=>"Cek jaringan\nPerbaiki perangkat",'progress'=>'Selesai','obstacle'=>'Menunggu kabel'],
            ['employee_id'=>$employeeId,'author'=>'Pegawai Uji','report_date'=>'2026-11-02','attendance'=>'Masuk','body'=>'Uji server'],
            ['employee_id'=>$employeeId,'author'=>'Pegawai Uji','report_date'=>'2026-11-07','attendance'=>'Masuk','body'=>'Tugas akhir pekan'],
            ['employee_id'=>$otherId,'author'=>'Pegawai Lain','report_date'=>'2026-11-03','attendance'=>'Masuk','body'=>'Rahasia pegawai lain'],
        ] as $row) DB::table('daily_reports')->insert($row+['created_at'=>now(),'updated_at'=>now()]);

        $reply=$this->send(['text'=>'/laporan November 2026'])->assertOk()->json('reply');
        $this->assertStringStartsWith('*Laporan November 2026*', $reply);
        $this->assertStringContainsString('1. Senin, 2 November 2026', $reply);
        $this->assertStringContainsString('6. Sabtu, 7 November 2026', $reply);
        $this->assertStringContainsString('7. Senin, 9 November 2026', $reply);
        $this->assertStringContainsString('  • Belum ada laporan.', $reply);
        $this->assertStringContainsString('      Cek jaringan', $reply);
        $this->assertStringContainsString('      Perbaiki perangkat', $reply);
        $this->assertStringContainsString('    - Kendala: Menunggu kabel', $reply);
        $this->assertStringContainsString('Tugas akhir pekan', $reply);
        $this->assertStringNotContainsString('Minggu, 8 November', $reply);
        $this->assertStringNotContainsString('Rahasia pegawai lain', $reply);
        $this->assertSame(22, preg_match_all('/^\d+\. /m', $reply));
        $this->assertLessThan(strpos($reply,'Tugas akhir pekan'), strpos($reply,'Cek jaringan'));

        DB::table('report_groups')->insert(['jid'=>'123@g.us']);
        $this->send(['text'=>'/laporan November 2026','group'=>'123@g.us'])->assertOk()->assertSee('Tugas akhir pekan');
        $this->send(['text'=>'/laporan November 2026','group'=>'456@g.us'])->assertNoContent();
        $this->send(['text'=>'/laporan November 2026','phone'=>'628550000000'])->assertNoContent();
        $this->send(['text'=>'/laporan Foo 2026'])->assertOk()->assertSee('Bulan atau tahun tidak valid');
        $this->assertStringContainsString('Gunakan /laporan BULAN TAHUN', $this->send(['text'=>'/laporan'])->assertOk()->json('reply'));
    }
    public function test_private_account_menu_and_selection():void
    {
        $id=DB::table('office_accounts')->insertGetId(['label'=>'Office Uji','username'=>encrypt('dummy-user',false),'password'=>encrypt('dummy-pass',false),'created_at'=>now(),'updated_at'=>now()]);
        $this->send(['text'=>'/akun Office'])->assertOk()->assertSee('Office Uji')->assertDontSee('dummy-pass');
        $this->send(['text'=>'1'])->assertOk()->assertSee('dummy-user')->assertSee('dummy-pass');
        $this->send(['text'=>'1','phone'=>'628440000000'])->assertNoContent();
        DB::table('report_groups')->insert(['jid'=>'123@g.us']);
        $this->send(['text'=>'/akun','group'=>'123@g.us'])->assertSee('chat pribadi')->assertDontSee('Office Uji');
        $this->travel(6)->minutes();
        $this->send(['text'=>'1'])->assertSee('Pilihan tidak tersedia');
    }
    public function test_access_checks_and_group_allowlist():void
    {
        $this->postJson('/api/laporan/masuk',$this->message)->assertForbidden();
        $this->send(['phone'=>'628330000000'])->assertNoContent();
        $this->send(['group'=>'123@g.us','mentioned'=>true])->assertNoContent();
        DB::table('report_groups')->insert(['jid'=>'123@g.us']);
        $this->send(['group'=>'123@g.us'])->assertNoContent();
        $this->send(['group'=>'123@g.us','mentioned'=>true])->assertOk();
        $this->send(['group'=>'123@g.us','text'=>'/penting'])->assertOk()->assertJsonStructure(['reply']);
        $this->send(['group'=>'456@g.us','text'=>'/penting'])->assertNoContent();
        DB::table('employees')->update(['active'=>false]);
        $this->send(['id'=>'message2'])->assertNoContent();
        $this->assertDatabaseCount('daily_reports',1);
    }
    public function test_dates_and_duplicate_messages():void
    {
        $d=['text'=>"Tanggal: 05 Oktober 2026\n1. Cek jaringan\n2. Cek server\nKehadiran: Masuk\nStatus: Selesai\nJenis: Permintaan"];
        $this->send($d)->assertOk();
        $this->send($d)->assertOk();
        $this->assertDatabaseCount('daily_reports',1);
        $this->assertDatabaseHas('daily_reports',['report_date'=>'2026-10-05','progress'=>'Selesai','attendance'=>'Masuk','initiative'=>'Permintaan','body'=>"1. Cek jaringan\n2. Cek server"]);
        $t=strtotime('2026-11-22T18:00:00Z');
        $this->assertSame('2026-11-23',ReportText::parse('22 November — Cek jaringan',$t)['report_date']);
        foreach (['22/10/2026','22-10-2026','2026-10-22'] as $date)
            $this->assertSame('2026-10-22',ReportText::parse($date.' — Cek jaringan',$t)['report_date']);
        foreach (['31/02/2026 cek perangkat','23/10/2026 dan 26/10/2026 cek server','halo',''] as $text)
            $this->send(['text'=>$text,'id'=>'bad'])->assertOk()->assertJsonStructure(['reply']);
        $this->assertDatabaseCount('daily_reports',1);
    }
    public function test_pdf_is_private_employee_scoped_and_admin_export_works():void
    {
        $this->send(['text'=>'22/10/2026 Cek perangkat'])->assertOk();
        $r=$this->send(['text'=>'/pdf 2026-10']);
        $r->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$r->getContent());
        DB::table('report_groups')->insert(['jid'=>'123@g.us']);
        $this->send(['group'=>'123@g.us','mentioned'=>true,'text'=>'/pdf 2026-10'])->assertJsonStructure(['reply']);
        DB::table('employees')->insert(['phone'=>'628330000000','name'=>'Pegawai Lain','position'=>'Teknisi']);
        $this->send(['phone'=>'628330000000','text'=>'/pdf 2026-10'])->assertJsonStructure(['reply']);
        $this->get('/laporan/bot/qr')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/laporan/bot')->assertOk();
        $id=DB::table('employees')->where('phone',$this->message['phone'])->value('id');
        $this->get('/laporan/bulanan?employee='.$id.'&month=2026-10')->assertHeader('Content-Type','application/pdf');
    }

    public function test_authenticated_admin_can_request_whatsapp_disconnect():void
    {
        $file=storage_path('app/private/report-bot/logout.request');
        @unlink($file);
        try {
            $this->post('/laporan/bot/lepas')->assertRedirect('/login');
            $this->actingAs(User::factory()->create());
            $this->get('/laporan/bot')->assertOk()->assertSee('Lepas WhatsApp');
            $this->post('/laporan/bot/lepas')->assertRedirect();
            $this->assertFileExists($file);
        } finally {
            @unlink($file);
        }
    }
}
