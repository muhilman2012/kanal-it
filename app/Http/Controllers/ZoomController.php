<?php
namespace App\Http\Controllers;
use App\Services\ZoomWorkflow as Zoom;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Process\Process;
class ZoomController extends Controller {
 private function period(Request $request):string {
  $period=(string)$request->query('period',now('Asia/Jakarta')->format('Y-m'));
  abort_unless($period==='all' || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period),422);
  return $period;
 }
 private function historyQuery(string $period) {
  $query=DB::table('zoom_requests');
  if($period!=='all'){
   $start=CarbonImmutable::createFromFormat('!Y-m',$period,'Asia/Jakarta');
   $query->where('starts_at','>=',$start->format('Y-m-d H:i:s'))
    ->where('starts_at','<',$start->addMonth()->format('Y-m-d H:i:s'));
  }
  return $query;
 }
 public function index(Request $request){
  $period=$this->period($request);
  return view('zoom.index',[
   'period'=>$period,
   'requests'=>$this->historyQuery($period)->orderBy('starts_at')->orderBy('id')->paginate(20)->withQueryString(),
   'approvers'=>DB::table('zoom_approvers')->orderByDesc('priority')->orderBy('name')->get(),
   'accounts'=>DB::table('zoom_accounts')->orderBy('name')->get(),
  ]);
 }
 public function historyPdf(Request $request){
  $period=$this->period($request);
  $rows=$this->historyQuery($period)->leftJoin('zoom_accounts','zoom_accounts.id','=','zoom_requests.zoom_account_id')
   ->select('zoom_requests.*','zoom_accounts.name as account_name')->orderBy('starts_at')->orderBy('zoom_requests.id')->get();
  $label=$period==='all'?'Semua periode':CarbonImmutable::createFromFormat('!Y-m',$period,'Asia/Jakarta')->locale('id')->isoFormat('MMMM YYYY');
  $filename='Riwayat-Permintaan-Zoom-'.($period==='all'?'Semua':$period).'.pdf';
  $process=new Process(['/usr/bin/python3',base_path('scripts/zoom_history_pdf.py')]);
  $process->setInput(json_encode(['period'=>$label,'generated'=>now('Asia/Jakarta')->format('d/m/Y H:i').' WIB','rows'=>$rows],JSON_THROW_ON_ERROR));
  $process->mustRun();
  return response($process->getOutput(),200,[
   'Content-Type'=>'application/pdf',
   'Content-Disposition'=>HeaderUtils::makeDisposition('attachment',$filename),
   'Cache-Control'=>'private, no-store',
  ]);
 }
 public function show(int $id){$request=DB::table('zoom_requests')->find($id);abort_unless($request,404);return view('zoom.show',['item'=>$request,'logs'=>DB::table('zoom_request_logs')->where('zoom_request_id',$id)->orderBy('id')->get(),'accounts'=>DB::table('zoom_accounts')->where('active',true)->get()]);}
 public function updateRequest(Request $request,int $id){
  $item=DB::table('zoom_requests')->find($id);abort_unless($item,404);
  abort_unless(in_array($item->status,['pending','approved'],true),409,'Permintaan ini tidak dapat diedit.');
  $data=$request->validate([
   'date'=>'required|date_format:Y-m-d',
   'start'=>'required|date_format:H:i',
   'end'=>'required|date_format:H:i',
   'agenda'=>'required|string|max:255',
   'unit'=>'required|string|max:255',
   'requester_name'=>'required|string|max:255',
   'email'=>'nullable|email|max:255',
   'participants'=>'required|integer|min:1|max:100000',
   'cohost'=>'nullable|string|max:255',
   'recording'=>['required',\Illuminate\Validation\Rule::in(['Video','Audio','Tidak'])],
   'notes'=>'nullable|string|max:2000',
  ]);
  $start=CarbonImmutable::createFromFormat('!Y-m-d H:i',$data['date'].' '.$data['start'],'Asia/Jakarta');
  $end=CarbonImmutable::createFromFormat('!Y-m-d H:i',$data['date'].' '.$data['end'],'Asia/Jakarta');
  if($end<=$start || $start->diffInMinutes($end)>1440)
   return back()->withErrors(['end'=>'Jam selesai harus setelah jam mulai, maksimal 24 jam.'])->withInput();
  $newStart=$start->format('Y-m-d H:i:s');$newEnd=$end->format('Y-m-d H:i:s');
  $zoomChanged=$item->agenda!==$data['agenda'] || $item->starts_at!==$newStart || $item->ends_at!==$newEnd;
  if($item->status==='approved' && $item->zoom_account_id && DB::table('zoom_requests')
   ->where('id','!=',$id)->where('zoom_account_id',$item->zoom_account_id)->where('status','approved')
   ->where('starts_at','<',$newEnd)->where('ends_at','>',$newStart)->exists())
   return back()->withErrors(['start'=>'Jadwal baru bentrok dengan rapat lain pada akun Zoom yang sama.'])->withInput();
  if($item->status==='approved' && $zoomChanged){
   if($start<=now('Asia/Jakarta'))return back()->withErrors(['start'=>'Jadwal rapat yang sudah disetujui harus berada di masa depan.'])->withInput();
   $account=DB::table('zoom_accounts')->find($item->zoom_account_id);
   if(!$account || !$account->account_id || !$account->client_id || !$account->client_secret || !$item->zoom_meeting_id)
    return back()->withErrors(['agenda'=>'Kredensial atau ID rapat Zoom belum lengkap; perubahan jadwal dan judul tidak dapat disinkronkan.'])->withInput();
   try {
    $token=Http::withBasicAuth(Crypt::decryptString($account->client_id),Crypt::decryptString($account->client_secret))
     ->asForm()->timeout(20)->post('https://zoom.us/oauth/token',['grant_type'=>'account_credentials','account_id'=>Crypt::decryptString($account->account_id)])->throw()->json('access_token');
    Http::withToken($token)->timeout(25)->patch('https://api.zoom.us/v2/meetings/'.rawurlencode($item->zoom_meeting_id),[
     'topic'=>$data['agenda'],'start_time'=>$start->toIso8601String(),
     'duration'=>$start->diffInMinutes($end),'timezone'=>'Asia/Jakarta',
    ])->throw();
   } catch(\Throwable $error){
    return back()->withErrors(['agenda'=>'Zoom menolak pembaruan. Periksa kredensial dan scope meeting:update:meeting:admin, lalu coba lagi. Data permintaan belum berubah.'])->withInput();
   }
  }
  $values=[
   'starts_at'=>$newStart,'ends_at'=>$newEnd,'agenda'=>$data['agenda'],'unit'=>$data['unit'],
   'requester_name'=>$data['requester_name'],'email'=>$data['email']??null,
   'participants'=>$data['participants'],'cohost'=>$data['cohost']??null,
   'recording'=>$data['recording'],'notes'=>$data['notes']??null,'updated_at'=>now(),
  ];
  abort_unless(DB::table('zoom_requests')->where('id',$id)->where('status',$item->status)->update($values),409,'Status permintaan berubah. Muat ulang halaman.');
  Zoom::log($id,'details_updated',$request->user()->email,'Detail permintaan diperbarui melalui web.');
  $code=Zoom::code($item);
  $summary="Perubahan permintaan Zoom #{$code}.\n\nTanggal: ".$start->format('d/m/Y H:i')."–".$end->format('H:i')." WIB\nJudul: {$data['agenda']}\nUnit: {$data['unit']}\nPIC: {$data['requester_name']}\nPeserta: {$data['participants']}\nRekaman: {$data['recording']}";
  if($item->status==='pending'){
   DB::table('zoom_outbox')->where('zoom_request_id',$id)->whereNull('sent_at')->delete();
   $approvers=DB::table('zoom_approvers')->where('active',true)->get();
   $priorityWindow=$item->priority_approver_id && now()->lt(CarbonImmutable::parse($item->created_at)->addMinutes(15));
   $accountLines=[];
   foreach(DB::table('zoom_accounts')->where('active',true)->orderBy('id')->get() as $account){
    $conflict=DB::table('zoom_requests')->where('zoom_account_id',$account->id)->where('status','approved')
     ->where('starts_at','<',$newEnd)->where('ends_at','>',$newStart)->exists();
    $accountLines[]=$account->id.'. '.$account->name.($conflict?' · BENTROK':' · tersedia');
   }
   $approvalMessage=$summary."\n\nAkun Zoom:\n".implode("\n",$accountLines)
    ."\n\nBalas /setuju {$code} NOMOR_AKUN atau /tolak {$code} ALASAN.";
   foreach($approvers as $approver){
    $deferred=$priorityWindow && $approver->id!==$item->priority_approver_id;
    Zoom::notify($approver->phone,$approvalMessage,
     $id,$deferred?CarbonImmutable::parse($item->created_at)->addMinutes(15):null,$deferred);
   }
   Zoom::notify($item->phone,$summary,$id);
  } else {
   Zoom::notify($item->phone,$summary."\nLink peserta: ".$item->join_url,$id);
   $approverPhone=DB::table('zoom_approvers')->where('id',$item->approver_id)->value('phone');
   if($approverPhone)Zoom::notify($approverPhone,$summary,$id);
  }
  return redirect('/zoom/'.$id)->with('status','Detail permintaan Zoom diperbarui'.($zoomChanged && $item->status==='approved'?' dan disinkronkan ke Zoom.':'.'));
 }
 public function approver(Request $request, ?int $id=null){
  $phone=preg_replace('/[\s()+-]/','',(string)$request->input('phone'));
  $request->merge(['phone'=>preg_replace('/^0/','62',$phone)]);
  $data=$request->validate(['name'=>'required|string|max:100','phone'=>['required','regex:/^[1-9][0-9]{7,14}$/',\Illuminate\Validation\Rule::unique('zoom_approvers')->ignore($id)]]);
  $data['active']=$request->boolean('active');$data['priority']=$request->boolean('priority');$data['updated_at']=now();
  if($data['priority'])DB::table('zoom_approvers')->where('id','!=',$id??0)->update(['priority'=>false,'updated_at'=>now()]);
  if($id){abort_unless(DB::table('zoom_approvers')->where('id',$id)->exists(),404);DB::table('zoom_approvers')->where('id',$id)->update($data);}
  else DB::table('zoom_approvers')->insert($data+['created_at'=>now()]);
  return back()->with('status','Data penyetuju tersimpan.');
 }
 public function deleteApprover(int $id){
  $approver=DB::table('zoom_approvers')->find($id);abort_unless($approver,404);
  if(DB::table('zoom_requests')->where('approver_id',$id)->orWhere('priority_approver_id',$id)->exists())return back()->withErrors(['approver'=>'Penyetuju memiliki riwayat permintaan. Nonaktifkan agar log tetap utuh.']);
  DB::table('zoom_approvers')->where('id',$id)->delete();return back()->with('status','Penyetuju dihapus.');
 }
 public function account(Request $request){$data=$request->validate(['name'=>'required|string|max:100','host_email'=>'required|email|max:255','account_id'=>'nullable|string|max:255','client_id'=>'nullable|string|max:255','client_secret'=>'nullable|string|max:255','active'=>'nullable|boolean']);$id=$request->integer('id');$values=['name'=>$data['name'],'host_email'=>$data['host_email'],'active'=>$request->boolean('active'),'updated_at'=>now()];foreach(['account_id','client_id','client_secret'] as $field)if($request->filled($field))$values[$field]=Crypt::encryptString($data[$field]);if($id){abort_unless(DB::table('zoom_accounts')->where('id',$id)->exists(),404);DB::table('zoom_accounts')->where('id',$id)->update($values);}else DB::table('zoom_accounts')->insert($values+['created_at'=>now()]);return back()->with('status','Akun Zoom tersimpan.');}
 public function deleteAccount(int $id){
  abort_unless(DB::table('zoom_accounts')->where('id',$id)->exists(),404);
  if(DB::table('zoom_requests')->where('zoom_account_id',$id)->exists())return back()->withErrors(['account'=>'Akun Zoom memiliki riwayat permintaan. Nonaktifkan agar log tetap utuh.']);
  DB::table('zoom_accounts')->where('id',$id)->delete();return back()->with('status','Akun Zoom dihapus.');
 }
 public function deleteRequest(int $id){
  $item=DB::table('zoom_requests')->find($id);abort_unless($item,404);
  $finished=in_array($item->status,['rejected','failed']) || ($item->status==='approved' && $item->ends_at<now('Asia/Jakarta')->format('Y-m-d H:i:s'));
  if(!$finished)return back()->withErrors(['request'=>'Hanya riwayat yang selesai dapat dihapus.']);
  DB::table('zoom_requests')->where('id',$id)->delete();
  return redirect('/zoom')->with('status','Riwayat permintaan Zoom dihapus.');
 }
 public function approve(Request $request,int $id){$data=$request->validate(['account_id'=>'required|integer|exists:zoom_accounts,id']);$item=DB::table('zoom_requests')->find($id);abort_unless($item,404);$actor=$request->user()->email;return back()->with('status',Zoom::create($id,(int)$data['account_id'],$actor));}
 public function reject(Request $request,int $id){$data=$request->validate(['reason'=>'required|string|max:1000']);$item=DB::table('zoom_requests')->find($id);abort_unless($item,404);abort_unless(DB::table('zoom_requests')->where('id',$id)->where('status','pending')->update(['status'=>'rejected','rejection_reason'=>$data['reason'],'updated_at'=>now()]),409);Zoom::log($id,'rejected',$request->user()->email,$data['reason']);Zoom::notify($item->phone,'Permintaan Zoom #'.$id.' ditolak. Alasan: '.$data['reason']);return back()->with('status','Permintaan ditolak.');}
}
