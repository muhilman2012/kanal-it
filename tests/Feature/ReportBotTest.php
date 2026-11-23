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
}