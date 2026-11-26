<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class ZoomChat {
 public static function handle(string $phone,string $text,string $messageId,int $timestamp):array {
  $approver=DB::table('zoom_approvers')->where('phone',$phone)->where('active',true)->first();
  if($approver && preg_match('~^/setuju\s+([A-Z0-9-]+)\s+(\d+)$~i',$text,$m))return ['reply'=>ZoomWorkflow::create((int)(ZoomWorkflow::find($m[1])?->id??0),(int)$m[2],$phone)];
  if($approver && preg_match('~^/tolak\s+([A-Z0-9-]+)\s+(.+)$~is',$text,$m)){$item=ZoomWorkflow::find($m[1]);if(!$item || $item->status!=='pending')return ['reply'=>'Permintaan tidak tersedia.'];if($item->priority_approver_id && now()->lt(\Carbon\Carbon::parse($item->created_at)->addMinutes(15)) && $phone!==DB::table('zoom_approvers')->where('id',$item->priority_approver_id)->value('phone'))return ['reply'=>'Penyetuju prioritas mendapat waktu 15 menit pertama. Silakan tunggu notifikasi berikutnya.'];if(!DB::table('zoom_requests')->where('id',$item->id)->where('status','pending')->update(['status'=>'rejected','approver_id'=>$approver->id,'rejection_reason'=>mb_substr($m[2],0,1000),'updated_at'=>now()]))return ['reply'=>'Permintaan sudah diproses.'];ZoomWorkflow::log($item->id,'rejected',$phone,$m[2]);ZoomWorkflow::notify($item->phone,'Permintaan Zoom #'.ZoomWorkflow::code($item).' ditolak.' . "\n\nAlasan: ".$m[2],$item->id);ZoomWorkflow::resolved($item->id,$phone,'ditolak');return ['reply'=>'Permintaan #'.ZoomWorkflow::code($item).' ditolak.'];}
  $key='zoom-draft:'.$phone;
  if(strtolower($text)==='/zoom'){$draft=['step'=>0,'fields'=>[]];Cache::put($key,$draft,now()->addMinutes(30));return ['reply'=>self::prompt(0)];}
  $draft=Cache::get($key);if(!$draft)return ['reply'=>'Kirim /zoom untuk mulai permintaan baru.'];
  if(strtolower($text)==='/batal'){Cache::forget($key);return ['reply'=>'Draf permintaan Zoom dibatalkan.'];}
  $fields=['date','start','end','agenda','unit','participants','name','email','cohost','recording','notes'];$step=$draft['step'];
  if($step===count($fields)){
   if(strtolower($text)!=='kirim')return ['reply'=>self::verification($draft['fields'])];
   $f=$draft['fields'];$start=ZoomWorkflow::schedule($f['date'],$f['start']);$end=ZoomWorkflow::schedule($f['date'],$f['end']);
   if($end<=$start)return ['reply'=>'Jam selesai harus setelah jam mulai. Kirim /batal lalu mulai lagi.'];
   $id=DB::table('zoom_requests')->insertGetId(['phone'=>$phone,'requester_name'=>$f['name'],'email'=>$f['email']?:null,'unit'=>$f['unit'],'agenda'=>$f['agenda'],'starts_at'=>$start->format('Y-m-d H:i:s'),'ends_at'=>$end->format('Y-m-d H:i:s'),'participants'=>(int)$f['participants'],'cohost'=>$f['cohost']?:null,'recording'=>$f['recording'],'notes'=>$f['notes']?:null,'source_message_id'=>$messageId,'created_at'=>now(),'updated_at'=>now()]);Cache::forget($key);$code='Z-'.now('Asia/Jakarta')->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));DB::table('zoom_requests')->where('id',$id)->update(['code'=>$code]);ZoomWorkflow::log($id,'requested',$phone);
   $accounts=DB::table('zoom_accounts')->where('active',true)->get();$lines=['Permintaan Zoom #'.$code,self::requestDetails($f),'Akun Zoom'];
   foreach($accounts as $account){$count=DB::table('zoom_requests')->where('zoom_account_id',$account->id)->where('status','approved')->whereDate('starts_at',$f['date'])->count();$conflict=DB::table('zoom_requests')->where('zoom_account_id',$account->id)->where('status','approved')->where('starts_at','<',$end->format('Y-m-d H:i:s'))->where('ends_at','>',$start->format('Y-m-d H:i:s'))->exists();$lines[]=$account->id.'. '.$account->name.' · '.$count.' rapat hari itu'.($conflict?' · BENTROK':' · tersedia');}
   $lines[]="\nBalas /setuju {$code} NOMOR_AKUN atau /tolak {$code} ALASAN.\nKode {$code} adalah identitas permintaan Zoom; angka pada daftar Akun Zoom adalah nomor akun yang dipilih.";
   $body=implode("\n",$lines);$approvers=DB::table('zoom_approvers')->where('active',true)->orderByDesc('priority')->orderBy('id')->get();$priority=$approvers->first(fn($a)=>(bool)$a->priority);$hasPriority=(bool)$priority;if($priority)DB::table('zoom_requests')->where('id',$id)->update(['priority_approver_id'=>$priority->id]);
   foreach($approvers as $a)ZoomWorkflow::notify($a->phone,$body,$id,$hasPriority&&!$a->priority?now()->addMinutes(15):null,$hasPriority&&!$a->priority);
   return ['reply'=>'Permintaan Zoom #'.$code.' terkirim untuk persetujuan.'];
  }
  $value=trim($text);if($value==='-' && in_array($fields[$step],['email','cohost','notes']))$value='';
  if($fields[$step]==='date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))return ['reply'=>'Gunakan tanggal YYYY-MM-DD.'];
  if(in_array($fields[$step],['start','end']) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$value))return ['reply'=>'Gunakan jam HH:MM dalam WIB.'];
  if($fields[$step]==='participants' && (!ctype_digit($value)|| (int)$value<1))return ['reply'=>'Jumlah peserta harus angka positif.'];
  if($fields[$step]==='recording' && !in_array(strtolower($value),['video','audio','tidak']))return ['reply'=>'Balas Video, Audio, atau Tidak.'];
  $draft['fields'][$fields[$step]]=$value;$draft['step']++;Cache::put($key,$draft,now()->addMinutes(30));return ['reply'=>$draft['step']===count($fields)?self::verification($draft['fields']):self::prompt($draft['step'])];
 }
 private static function requestDetails(array $f):string {
  $date=CarbonImmutable::createFromFormat('!Y-m-d',$f['date'],'Asia/Jakarta')->format('d-m-Y');
  $needs=['Rekaman: '.$f['recording']];if(!empty($f['cohost']))$needs[]='Co-host: '.$f['cohost'];if(!empty($f['notes']))$needs[]=$f['notes'];
  return "Tanggal Rapat: {$date} {$f['start']}–{$f['end']} WIB\n\nJudul Kegiatan: {$f['agenda']}\n\nUnit Kerja: {$f['unit']}\n\nNama PIC: {$f['name']}\n\nJumlah Peserta: {$f['participants']} peserta\n\nKebutuhan:\n- ".implode("\n- ",$needs)."\n";
 }
 private static function verification(array $f):string {return "Periksa permintaan Zoom:\n\n".rtrim(self::requestDetails($f))."\n\nBalas KIRIM untuk mengajukan atau /batal.";}
 private static function prompt(int $i):string {return ['Tanggal kegiatan (YYYY-MM-DD)?','Jam mulai WIB (HH:MM)?','Jam selesai WIB (HH:MM)?','Agenda kegiatan?','Unit kerja pelaksana?','Jumlah peserta?','Nama pemohon?','Email pemohon? Balas - jika kosong.','Pegawai co-host? Balas - jika kosong.','Rekaman lokal: Video, Audio, atau Tidak?','Keterangan lain? Balas - jika kosong.'][$i];}
 private static function summary(array $f):string {return $f['date'].' '.$f['start'].'–'.$f['end'].' WIB · '.$f['agenda'].' · '.$f['unit'].' · '.$f['name'].' · '.$f['participants'].' peserta · Rekaman: '.$f['recording'];}
}