<?php
namespace App\Http\Controllers;
use App\Services\ZoomWorkflow as Zoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
class ZoomController extends Controller {
 public function index(){return view('zoom.index',['requests'=>DB::table('zoom_requests')->orderByDesc('id')->paginate(20),'approvers'=>DB::table('zoom_approvers')->orderByDesc('priority')->orderBy('name')->get(),'accounts'=>DB::table('zoom_accounts')->orderBy('name')->get()]);}
 public function show(int $id){$request=DB::table('zoom_requests')->find($id);abort_unless($request,404);return view('zoom.show',['item'=>$request,'logs'=>DB::table('zoom_request_logs')->where('zoom_request_id',$id)->orderBy('id')->get(),'accounts'=>DB::table('zoom_accounts')->where('active',true)->get()]);}
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
 public function approve(Request $request,int $id){$data=$request->validate(['account_id'=>'required|integer|exists:zoom_accounts,id']);$item=DB::table('zoom_requests')->find($id);abort_unless($item,404);$actor=$request->user()->email;return back()->with('status',Zoom::create($id,(int)$data['account_id'],$actor));}
 public function reject(Request $request,int $id){$data=$request->validate(['reason'=>'required|string|max:1000']);$item=DB::table('zoom_requests')->find($id);abort_unless($item,404);abort_unless(DB::table('zoom_requests')->where('id',$id)->where('status','pending')->update(['status'=>'rejected','rejection_reason'=>$data['reason'],'updated_at'=>now()]),409);Zoom::log($id,'rejected',$request->user()->email,$data['reason']);Zoom::notify($item->phone,'Permintaan Zoom #'.$id.' ditolak. Alasan: '.$data['reason']);return back()->with('status','Permintaan ditolak.');}
}