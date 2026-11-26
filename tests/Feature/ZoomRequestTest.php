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
}
