<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class ZoomWorkflow {
 public static function log(int $id,string $event,?string $actor=null,?string $detail=null):void {DB::table('zoom_request_logs')->insert(['zoom_request_id'=>$id,'event'=>$event,'actor'=>$actor,'detail'=>$detail,'created_at'=>now(),'updated_at'=>now()]);}
 public static function code(object $request):string {return $request->code ?: (string)$request->id;}
 public static function find(string $identifier):?object {return ctype_digit($identifier)?DB::table('zoom_requests')->find((int)$identifier):DB::table('zoom_requests')->where('code',strtoupper($identifier))->first();}
 public static function notify(string $phone,string $text,?int $requestId=null,?\DateTimeInterface $availableAt=null,bool $pendingOnly=false):void {DB::table('zoom_outbox')->insert(['phone'=>$phone,'body'=>$text,'zoom_request_id'=>$requestId,'available_at'=>$availableAt??now(),'pending_only'=>$pendingOnly,'created_at'=>now(),'updated_at'=>now()]);}
 public static function resolved(int $id,string $actor,string $outcome):void {
  $request=DB::table('zoom_requests')->find($id);$priority=$request?->priority_approver_id?DB::table('zoom_approvers')->find($request->priority_approver_id):null;
  if($priority && $priority->phone!==$actor)self::notify($priority->phone,'Permintaan Zoom #'.self::code(DB::table('zoom_requests')->find($id)).' sudah '.$outcome.' oleh penyetuju lain.',$id);
 }
 public static function meetingId(string $id):string {return strlen($id)===11?substr($id,0,3).' '.substr($id,3,4).' '.substr($id,7):implode(' ',str_split($id,4));}
 public static function schedule(string $date,string $time):CarbonImmutable {return CarbonImmutable::createFromFormat('!Y-m-d H:i',''.$date.' '.$time,'Asia/Jakarta');}
 public static function create(int $id,int $accountId,string $actor):string {
  $request=DB::table('zoom_requests')->find($id);$account=DB::table('zoom_accounts')->where('active',true)->find($accountId);
  if(!$request || !$account || $request->status!=='pending') return 'Permintaan atau akun Zoom tidak tersedia.';
  if($request->priority_approver_id && now()->lt(\Carbon\Carbon::parse($request->created_at)->addMinutes(15)) && $actor!==DB::table('zoom_approvers')->where('id',$request->priority_approver_id)->value('phone'))return 'Penyetuju prioritas mendapat waktu 15 menit pertama. Silakan tunggu notifikasi berikutnya.';
  $overlap=DB::table('zoom_requests')->where('zoom_account_id',$accountId)->where('status','approved')->where('starts_at','<',$request->ends_at)->where('ends_at','>',$request->starts_at)->exists();
  if($overlap)return 'Jadwal akun Zoom tersebut bentrok.';
  if(!$account->account_id || !$account->client_id || !$account->client_secret || !$account->host_email)return 'Kredensial akun Zoom belum lengkap.';
  if(!DB::table('zoom_requests')->where('id',$id)->where('status','pending')->update(['status'=>'creating','zoom_account_id'=>$accountId,'approver_id'=>DB::table('zoom_approvers')->where('phone',$actor)->value('id'),'updated_at'=>now()]))return 'Permintaan sedang diproses.';
  self::log($id,'approved_by',$actor,$account->name);
  try {
   $client=Crypt::decryptString($account->client_id);$secret=Crypt::decryptString($account->client_secret);$accountKey=Crypt::decryptString($account->account_id);
   $token=Http::withBasicAuth($client,$secret)->asForm()->timeout(20)->post('https://zoom.us/oauth/token',['grant_type'=>'account_credentials','account_id'=>$accountKey])->throw()->json('access_token');
   $start=CarbonImmutable::parse($request->starts_at,'Asia/Jakarta');$end=CarbonImmutable::parse($request->ends_at,'Asia/Jakarta');
   $meeting=Http::withToken($token)->timeout(25)->post('https://api.zoom.us/v2/users/'.rawurlencode($account->host_email).'/meetings',['topic'=>$request->agenda,'type'=>2,'start_time'=>$start->toIso8601String(),'duration'=>$start->diffInMinutes($end),'timezone'=>'Asia/Jakarta','settings'=>['waiting_room'=>true]])->throw()->json();
   if(empty($meeting['id']) || empty($meeting['join_url']))throw new \RuntimeException('Zoom tidak mengembalikan ID dan link meeting.');
   DB::table('zoom_requests')->where('id',$id)->update(['status'=>'approved','zoom_meeting_id'=>(string)$meeting['id'],'join_url'=>$meeting['join_url'],'start_url'=>isset($meeting['start_url'])?Crypt::encryptString($meeting['start_url']):null,'updated_at'=>now()]);
   self::log($id,'meeting_created',$actor,(string)$meeting['id']);
   $code=self::code($request);$passcode=(string)($meeting['password']??'');
   $details="Permintaan Zoom #{$code} disetujui.\n\nTopik: {$request->agenda}\n\nWaktu: ".$start->format('d/m/Y H:i')." WIB\n\nLink peserta: {$meeting['join_url']}\n\nID Rapat: ".self::meetingId((string)$meeting['id'])."\nKode Sandi: ".($passcode?:'Lihat tautan peserta');
   self::notify($request->phone,$details,$id);
   self::notify($actor,"Zoom #{$code} dibuat pada {$account->name}.\n\n".$details,$id);
   self::resolved($id,$actor,'disetujui');
   return 'Disetujui. Zoom #'.$code.' dibuat pada '.$account->name.'.';
  }catch(\Throwable $e){DB::table('zoom_requests')->where('id',$id)->update(['status'=>'failed','updated_at'=>now()]);self::log($id,'zoom_failed',$actor,mb_substr($e->getMessage(),0,500));return 'Persetujuan tercatat, pembuatan Zoom gagal. Periksa log permintaan #'.self::code($request).' di web.';}
 }
}