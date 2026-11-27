<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendancePinsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_and_optional_obstacle(): void
    {
        foreach (['cuti','izin','sakit'] as $command) {
            $row=ReportText::parse('/'.$command.' 25 nov 2026',1);
            $this->assertSame(['report_date'=>'2026-11-25','attendance'=>ucfirst($command),'body'=>''],$row);
        }
        foreach (["\nKendala : "," Kendala: "] as $separator) {
            $row=ReportText::parse('Cek perangkat'.$separator.'Suku cadang habis',1);
            $this->assertSame('Masuk',$row['attendance']);
            $this->assertSame('Cek perangkat',$row['body']);
            $this->assertSame('Suku cadang habis',$row['obstacle']);
        }
        foreach (['/cuti','/sakit 31 feb 2026','/izin 25 nov'] as $cmd) {
            try { ReportText::parse($cmd,1); $this->fail('Tanggal tidak valid diterima'); }
            catch (\InvalidArgumentException $error) { $this->assertNotEmpty($error->getMessage()); }
        }
    }

    public function test_pin_is_private_and_available_to_registered_employee(): void
    {
        $this->get('/penting')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $bytes='Lampiran uji internal';
        $this->post('/penting',['title'=>'Panduan','url'=>'https://example.org/it','file'=>UploadedFile::fake()->createWithContent('Panduan.txt',$bytes)])->assertRedirect('/penting');
        $id=DB::table('pinned_posts')->value('id');
        $this->assertNotNull($id);
        $this->get('/penting')->assertOk()->assertSee('Panduan');
        $this->assertSame($bytes,$this->get('/penting/'.$id.'/file')->getContent());
        config(['services.report_bot.token'=>'test']);
        $payload=['phone'=>'628110000000','id'=>'test','text'=>'/penting','timestamp'=>1,'mentioned'=>false];
        $send=fn($d)=>$this->withToken('test')->postJson('/api/laporan/masuk',$d);
        $send($payload)->assertNoContent();
        DB::table('employees')->insert(['name'=>'pegawai uji','phone'=>$payload['phone'],'position'=>'IT']);
        $send($payload)->assertOk()->assertSee('Panduan');
        DB::table('pinned_posts')->insert([
            ['title'=>'Watermark 2026','url'=>'https://example.org/watermark','created_at'=>now(),'updated_at'=>now()],
            ['title'=>'Panduan VPN','url'=>'https://example.org/vpn','created_at'=>now(),'updated_at'=>now()],
        ]);
        $this->assertSame("*Postingan penting*\n\n*1. Panduan*\n*2. Watermark 2026*\n*3. Panduan VPN*\n\nKirim /penting 3 untuk melihat postingan tersebut.",
            $send($payload)->assertOk()->json('reply'));
        $payload['text']='/penting '.$id;
        $send($payload)->assertJsonPath('media.data',base64_encode($bytes))->assertJsonPath('media.name','Panduan.txt');
        $this->assertDatabaseCount('daily_reports',0);
        $payload['text']='/izin 25 nov 2026';
        $send($payload)->assertOk();
        $this->assertDatabaseHas('daily_reports',['attendance'=>'Izin','report_date'=>'2026-11-25']);
        $payload['text']='/pdf 2026-11';
        $pdf=$send($payload)->assertOk()->assertHeader('X-Report-Filename','Laporan-Pegawai%20Uji-November-2026.pdf');
        $this->assertStringContainsString('Laporan-Pegawai Uji-November-2026.pdf',$pdf->headers->get('Content-Disposition'));
        $this->delete('/penting/'.$id)->assertRedirect('/penting');
        $this->get('/penting/'.$id.'/file')->assertNotFound();
    }
}
