<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportAdminController extends Controller
{
    private function state(): array
    {
        $file=storage_path('app/private/report-bot/state.json');
        $state=is_file($file) ? json_decode(file_get_contents($file),true) : [];
        if (($state['updated']??0)<time()-65) return ['status'=>'Layanan belum tersambung'];
        return $state;
    }

    public function index()
    {
        return view('report-admin',['employees'=>DB::table('employees')->orderBy('name')->get(),
            'linkedEmployees'=>DB::table('users')->whereNotNull('employee_id')->pluck('employee_id')->all(),
            'enabled'=>DB::table('report_groups')->pluck('jid')->all(),'state'=>$this->state()]);
    }

    public function disconnect()
    {
        $directory=storage_path('app/private/report-bot');
        if (!is_dir($directory)) mkdir($directory,0700,true);
        file_put_contents($directory.'/logout.request',now()->toIso8601String(),LOCK_EX);
        return back()->with('status','Permintaan melepas WhatsApp dikirim. Perbarui status untuk melihat QR baru.');
    }

    public function qr()
    {
        $state=$this->state();
        abort_unless(($state['qr']??null) && ($state['qrAt']??0)>time()-55,404);
        return response(base64_decode($state['qr']),200,['Content-Type'=>'image/png','Cache-Control'=>'no-store, private']);
    }

    public function employee(Request $request, ?int $id=null)
    {
        $raw=preg_replace('/[\s()\-]/','',(string)$request->input('phone'));
        $request->merge(['phone'=>preg_replace('/^0/','62',ltrim($raw,'+'))]);
        $d=$request->validate(['name'=>'required|string|max:100','phone'=>['required','regex:/^[1-9][0-9]{7,14}$/',Rule::unique('employees')->ignore($id)],'position'=>'required|string|max:100']);
        $d['active']=$request->boolean('active');
        if ($id) {
            abort_unless(DB::table('employees')->where('id',$id)->exists(),404);
            DB::table('employees')->where('id',$id)->update($d);
        } else DB::table('employees')->insert($d);
        return back()->with('status','Data pegawai tersimpan.');
    }

    public function group(Request $request)
    {
        $d=$request->validate(['jid'=>['required','regex:/^[0-9-]+@g\.us$/','max:100']]);
        if ($request->boolean('enabled')) DB::table('report_groups')->insertOrIgnore($d);
        else DB::table('report_groups')->where($d)->delete();
        return back()->with('status','Akses grup diperbarui.');
    }

    public function assign(Request $request, int $id)
    {
        $d=$request->validate(['employee_id'=>'required|integer|exists:employees,id']);
        $employee=DB::table('employees')->find($d['employee_id']);
        abort_unless(DB::table('daily_reports')->where('id',$id)->whereNull('employee_id')->update([
            'employee_id'=>$employee->id,'author'=>$employee->name,'updated_at'=>now(),
        ]),409);
        return back()->with('status','Laporan telah dikaitkan dengan pegawai.');
    }

    public function pdf(Request $request)
    {
        $d=$request->validate(['employee'=>'required|integer|exists:employees,id','month'=>'required|date_format:Y-m']);
        $e=DB::table('employees')->find($d['employee']);
        if (!DB::table('daily_reports')->where('employee_id',$e->id)->where('report_date','like',$d['month'].'-%')->exists()) return back()->withErrors(['month'=>'Belum ada laporan pegawai untuk bulan tersebut.']);
        return MonthlyReport::download($e,$d['month']);
    }
}
