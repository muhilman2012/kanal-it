<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ZoomRequestTest extends TestCase {
 use RefreshDatabase;
 public function test_request_notifies_only_registered_approvers_and_requires_their_number():void {
  DB::table('zoom_approvers')->insert(['name'=>'Joko','phone'=>'6281541171795','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('zoom_accounts')->insert(['name'=>'Zoom 1','host_email'=>'zoom@example.test','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $phone='628111111111';$id=1;
  $send=function($text,$from=null) use($phone,&$id){return \App\Services\ZoomChat::handle($from??$phone,$text,'wa-'.($id++),time())['reply'];};
  $this->assertSame('Tanggal kegiatan (YYYY-MM-DD)?',$send('/zoom'));
  foreach(['2026-12-01','09:00','10:00','Koordinasi IT','Biro','25','Pemohon','-','-','Tidak'] as $answer)$send($answer);
  $verification=$send('-');$this->assertStringContainsString("Tanggal Rapat: 01-12-2026 09:00–10:00 WIB\n\nJudul Kegiatan: Koordinasi IT",$verification);$this->assertStringContainsString("Jumlah Peserta: 25 peserta\n\nKebutuhan:\n- Rekaman: Tidak\n\nBalas KIRIM",$verification);
  $this->assertStringContainsString('Permintaan Zoom #Z-',$send('KIRIM'));
  $this->assertDatabaseHas('zoom_requests',['status'=>'pending','phone'=>$phone]);
  $this->assertDatabaseCount('zoom_outbox',1);
  $this->assertStringContainsString('/setuju Z-',DB::table('zoom_outbox')->first()->body);
 }
 public function test_approver_can_be_edited_deactivated_and_deleted():void {
  $this->actingAs(\App\Models\User::factory()->create());
  $this->post('/zoom/approver',['name'=>'Joko','phone'=>'081541171795','active'=>1])->assertRedirect();
  $id=DB::table('zoom_approvers')->value('id');
  $this->get('/zoom')->assertOk()->assertSee('Joko')->assertSee('Edit');
  $this->put('/zoom/approver/'.$id,['name'=>'Joko Prihantoro','phone'=>'081541171795'])->assertRedirect();
  $this->assertDatabaseHas('zoom_approvers',['id'=>$id,'active'=>false,'name'=>'Joko Prihantoro','phone'=>'6281541171795']);
  $this->delete('/zoom/approver/'.$id)->assertRedirect();
  $this->assertDatabaseCount('zoom_approvers',0);
 }
 public function test_priority_approver_receives_request_first_and_fallback_after_fifteen_minutes():void {
  \Carbon\Carbon::setTestNow('2026-11-25 09:00:00');
  try {
   DB::table('zoom_approvers')->insert([
    ['name'=>'Utama','phone'=>'628111111111','priority'=>true,'active'=>true,'created_at'=>now(),'updated_at'=>now()],
    ['name'=>'Cadangan','phone'=>'628222222222','priority'=>false,'active'=>true,'created_at'=>now(),'updated_at'=>now()],
   ]);
   DB::table('zoom_accounts')->insert(['name'=>'Zoom 1','host_email'=>'zoom@example.test','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   $n=0;$send=function($phone,$text)use(&$n){return \App\Services\ZoomChat::handle($phone,$text,'priority-'.(++$n),time())['reply'];};
   $phone='628333333333';$send($phone,'/zoom');
   foreach(['2026-12-01','09:00','10:00','Koordinasi IT','Biro','25','Pemohon','-','-','Video','-'] as $answer)$send($phone,$answer);
   $reply=$send($phone,'KIRIM');$this->assertMatchesRegularExpression('/Permintaan Zoom #Z-\d{6}-[A-F0-9]{6}/',$reply);
   $item=DB::table('zoom_requests')->first();$this->assertSame(1,$item->priority_approver_id);
   $messages=DB::table('zoom_outbox')->orderBy('id')->get();$this->assertCount(2,$messages);$this->assertEquals(0,$messages[0]->pending_only);$this->assertEquals(1,$messages[1]->pending_only);
   $this->assertStringContainsString('Tanggal Rapat: 01-12-2026 09:00–10:00 WIB',$messages[0]->body);
   $this->assertStringContainsString('Kode '.$item->code.' adalah identitas permintaan Zoom',$messages[0]->body);
   config()->set('services.report_bot.token','test-token');$this->getJson('/api/zoom/outbox',['Authorization'=>'Bearer test-token'])->assertOk()->assertJsonCount(1);
   $this->assertStringContainsString('15 menit pertama',$send('628222222222','/tolak '.$item->code.' Tidak tersedia'));
   \Carbon\Carbon::setTestNow('2026-11-25 09:16:00');
   $this->getJson('/api/zoom/outbox',['Authorization'=>'Bearer test-token'])->assertOk()->assertJsonCount(2);
   $this->assertStringContainsString('ditolak',$send('628222222222','/tolak '.$item->code.' Tidak tersedia'));
   $this->getJson('/api/zoom/outbox',['Authorization'=>'Bearer test-token'])->assertOk()->assertJsonMissing(['id'=>$messages[1]->id]);
   $this->assertDatabaseHas('zoom_requests',['id'=>$item->id,'status'=>'rejected']);
   $notice=DB::table('zoom_outbox')->where('phone','628111111111')->orderByDesc('id')->first();$this->assertStringContainsString('sudah ditolak oleh penyetuju lain',$notice->body);
  } finally {\Carbon\Carbon::setTestNow();}
 }
 public function test_zoom_account_can_be_edited_deactivated_and_deleted():void {
  $this->actingAs(\App\Models\User::factory()->create());
  $this->post('/zoom/account',['name'=>'Zoom 1','host_email'=>'host@example.test','account_id'=>'account','client_id'=>'client','client_secret'=>'secret','active'=>1])->assertRedirect();
  $id=DB::table('zoom_accounts')->value('id');
  $this->get('/zoom')->assertOk()->assertSee('host@example.test')->assertSee('Edit akun Zoom');
  $this->post('/zoom/account',['id'=>$id,'name'=>'Zoom Utama','host_email'=>'host@example.test'])->assertRedirect();
  $this->assertDatabaseHas('zoom_accounts',['id'=>$id,'name'=>'Zoom Utama','active'=>false]);
  $this->delete('/zoom/account/'.$id)->assertRedirect();$this->assertDatabaseCount('zoom_accounts',0);
 }

 public function test_approved_meeting_sends_formatted_details_and_informs_priority():void {
  \Carbon\Carbon::setTestNow('2026-11-25 09:16:00');
  try {
   \Illuminate\Support\Facades\Http::fake([
    'zoom.us/oauth/token'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'test-token']),
    'api.zoom.us/*'=>\Illuminate\Support\Facades\Http::response(['id'=>92634297947,'join_url'=>'https://zoom.us/j/92634297947','password'=>'564256','start_url'=>'https://zoom.us/s/92634297947']),
   ]);
   $priority=DB::table('zoom_approvers')->insertGetId(['name'=>'Utama','phone'=>'628111111111','priority'=>true,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('zoom_approvers')->insert(['name'=>'Cadangan','phone'=>'628222222222','priority'=>false,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   $account=DB::table('zoom_accounts')->insertGetId(['name'=>'Zoom 1','host_email'=>'zoom@example.test','account_id'=>\Illuminate\Support\Facades\Crypt::encryptString('account'),'client_id'=>\Illuminate\Support\Facades\Crypt::encryptString('client'),'client_secret'=>\Illuminate\Support\Facades\Crypt::encryptString('secret'),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   $id=DB::table('zoom_requests')->insertGetId(['code'=>'Z-261125-ABC123','phone'=>'628333333333','requester_name'=>'Pemohon','unit'=>'Biro','agenda'=>'Rapat IT','starts_at'=>'2026-12-01 09:00:00','ends_at'=>'2026-12-01 10:00:00','participants'=>20,'recording'=>'Video','priority_approver_id'=>$priority,'status'=>'pending','created_at'=>'2026-11-25 09:00:00','updated_at'=>now()]);
   $this->assertStringContainsString('Disetujui',\App\Services\ZoomWorkflow::create($id,$account,'628222222222'));
   $this->assertDatabaseHas('zoom_requests',['id'=>$id,'status'=>'approved','zoom_meeting_id'=>'92634297947']);
   $message=DB::table('zoom_outbox')->where('phone','628333333333')->first()->body;
   $this->assertStringContainsString('Permintaan Zoom #Z-261125-ABC123 disetujui.',$message);
   $this->assertStringContainsString('ID Rapat: 926 3429 7947',$message);
   $this->assertStringContainsString('Kode Sandi: 564256',$message);
   $this->assertStringContainsString('sudah disetujui oleh penyetuju lain',DB::table('zoom_outbox')->where('phone','628111111111')->first()->body);
  } finally {\Carbon\Carbon::setTestNow();}
 }

 public function test_finished_zoom_history_can_be_deleted_but_pending_request_is_kept():void {
  $this->actingAs(\App\Models\User::factory()->create());
  $old=DB::table('zoom_requests')->insertGetId(['phone'=>'628111111111','requester_name'=>'Pemohon','unit'=>'Biro','agenda'=>'Rapat lama','starts_at'=>'2026-01-01 09:00:00','ends_at'=>'2026-01-01 10:00:00','participants'=>5,'recording'=>'Tidak','status'=>'approved','created_at'=>now(),'updated_at'=>now()]);
  DB::table('zoom_request_logs')->insert(['zoom_request_id'=>$old,'event'=>'meeting_created','created_at'=>now(),'updated_at'=>now()]);
  DB::table('zoom_outbox')->insert(['zoom_request_id'=>$old,'phone'=>'628111111111','body'=>'Selesai','created_at'=>now(),'updated_at'=>now()]);
  $pending=DB::table('zoom_requests')->insertGetId(['phone'=>'628111111111','requester_name'=>'Pemohon','unit'=>'Biro','agenda'=>'Rapat baru','starts_at'=>'2026-12-01 09:00:00','ends_at'=>'2026-12-01 10:00:00','participants'=>5,'recording'=>'Tidak','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  $this->delete('/zoom/'.$pending)->assertRedirect();
  $this->assertDatabaseHas('zoom_requests',['id'=>$pending]);
  $this->get('/zoom/'.$old)->assertOk()->assertSee('Hapus riwayat');
  $this->delete('/zoom/'.$old)->assertRedirect('/zoom');
  $this->assertDatabaseMissing('zoom_requests',['id'=>$old]);
  $this->assertDatabaseCount('zoom_request_logs',0);
  $this->assertDatabaseCount('zoom_outbox',0);
 }

 public function test_history_month_filter_and_pdf_export_use_meeting_date():void {
  $this->actingAs(\App\Models\User::factory()->create());
  foreach([['date'=>'2026-11-10','agenda'=>'Rapat November'],['date'=>'2026-12-10','agenda'=>'Rapat Desember']] as $row)
   DB::table('zoom_requests')->insert(['phone'=>'628111111111','requester_name'=>'Pemohon','unit'=>'Biro','agenda'=>$row['agenda'],
    'starts_at'=>$row['date'].' 09:00:00','ends_at'=>$row['date'].' 10:00:00','participants'=>5,'recording'=>'Tidak',
    'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  $this->get('/zoom?period=2026-11')->assertOk()->assertSee('Rapat November')->assertDontSee('Rapat Desember');
  $this->get('/zoom?period=2026-12')->assertOk()->assertSee('Rapat Desember')->assertDontSee('Rapat November');
  $this->get('/zoom?period=all')->assertOk()->assertSee('Rapat November')->assertSee('Rapat Desember');
  $pdf=$this->get('/zoom/riwayat.pdf?period=2026-11')->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->assertStringStartsWith('%PDF-', $pdf->getContent());
  $this->assertStringContainsString('Riwayat-Permintaan-Zoom-2026-11.pdf',$pdf->headers->get('Content-Disposition'));
  $this->get('/zoom?period=2026-13')->assertStatus(422);
 }

 public function test_pending_request_can_be_edited_and_approvers_receive_updated_details():void {
  $this->actingAs(\App\Models\User::factory()->create());
  $approver=DB::table('zoom_approvers')->insertGetId(['name'=>'Joko','phone'=>'628111111111','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $account=DB::table('zoom_accounts')->insertGetId(['name'=>'Zoom 1','host_email'=>'zoom@example.test','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $id=DB::table('zoom_requests')->insertGetId(['code'=>'Z-261127-ABC123','phone'=>'628222222222','requester_name'=>'Pemohon',
   'unit'=>'Biro','agenda'=>'Rapat awal','starts_at'=>'2026-12-01 09:00:00','ends_at'=>'2026-12-01 10:00:00',
   'participants'=>5,'recording'=>'Tidak','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  DB::table('zoom_outbox')->insert(['zoom_request_id'=>$id,'phone'=>'628111111111','body'=>'Jadwal lama','created_at'=>now(),'updated_at'=>now()]);
  $this->get('/zoom/'.$id)->assertOk()->assertSee('Edit detail permintaan');
  $this->put('/zoom/'.$id,[
   'date'=>'2026-12-02','start'=>'10:00','end'=>'11:00','agenda'=>'Rapat revisi','unit'=>'Biro Baru',
   'requester_name'=>'Pemohon','email'=>'pic@example.test','participants'=>10,'cohost'=>'Hilman',
   'recording'=>'Video','notes'=>'Proyektor',
  ])->assertRedirect('/zoom/'.$id);
  $this->assertDatabaseHas('zoom_requests',['id'=>$id,'agenda'=>'Rapat revisi','starts_at'=>'2026-12-02 10:00:00','participants'=>10]);
  $this->assertDatabaseMissing('zoom_outbox',['body'=>'Jadwal lama']);
  $this->assertStringContainsString('Rapat revisi',DB::table('zoom_outbox')->where('phone','628111111111')->first()->body);
  $this->assertStringContainsString('Zoom 1',DB::table('zoom_outbox')->where('phone','628111111111')->first()->body);
  $this->assertDatabaseHas('zoom_request_logs',['zoom_request_id'=>$id,'event'=>'details_updated']);
  $this->put('/zoom/'.$id,[
   'date'=>'2026-12-02','start'=>'11:00','end'=>'10:00','agenda'=>'Salah','unit'=>'Biro',
   'requester_name'=>'Pemohon','participants'=>10,'recording'=>'Video',
  ])->assertSessionHasErrors('end');
 }

 public function test_approved_request_updates_zoom_before_local_data():void {
  $this->actingAs(\App\Models\User::factory()->create());
  $account=DB::table('zoom_accounts')->insertGetId(['name'=>'Zoom 1','host_email'=>'zoom@example.test',
   'account_id'=>\Illuminate\Support\Facades\Crypt::encryptString('account'),
   'client_id'=>\Illuminate\Support\Facades\Crypt::encryptString('client'),
   'client_secret'=>\Illuminate\Support\Facades\Crypt::encryptString('secret'),
   'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $id=DB::table('zoom_requests')->insertGetId(['phone'=>'628222222222','requester_name'=>'Pemohon','unit'=>'Biro','agenda'=>'Rapat awal',
   'starts_at'=>'2026-12-01 09:00:00','ends_at'=>'2026-12-01 10:00:00','participants'=>5,'recording'=>'Tidak',
   'status'=>'approved','zoom_account_id'=>$account,'zoom_meeting_id'=>'92634297947','join_url'=>'https://zoom.us/j/92634297947',
   'created_at'=>now(),'updated_at'=>now()]);
  $payload=['date'=>'2026-12-02','start'=>'10:00','end'=>'11:00','agenda'=>'Rapat revisi','unit'=>'Biro',
   'requester_name'=>'Pemohon','participants'=>10,'recording'=>'Video'];
  $allow=false;
  \Illuminate\Support\Facades\Http::fake(function($request)use(&$allow){
   return str_contains($request->url(),'oauth/token')
    ?\Illuminate\Support\Facades\Http::response(['access_token'=>'test-token'])
    :\Illuminate\Support\Facades\Http::response([],$allow?204:403);
  });
  $this->put('/zoom/'.$id,$payload)->assertSessionHasErrors('agenda');
  $this->assertDatabaseHas('zoom_requests',['id'=>$id,'agenda'=>'Rapat awal']);
  $allow=true;
  $this->put('/zoom/'.$id,$payload)->assertRedirect('/zoom/'.$id);
  $this->assertDatabaseHas('zoom_requests',['id'=>$id,'agenda'=>'Rapat revisi','starts_at'=>'2026-12-02 10:00:00']);
  \Illuminate\Support\Facades\Http::assertSent(fn($req)=>$req->method()==='PATCH' && str_contains($req->url(),'92634297947'));
  $this->assertStringContainsString('Rapat revisi',DB::table('zoom_outbox')->where('phone','628222222222')->first()->body);
 }
}
