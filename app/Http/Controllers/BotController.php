<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use App\Services\ReportText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BotController extends Controller
{
    public function receive(Request $request)
    {
        $token=config('services.report_bot.token');
        abort_unless($token && hash_equals($token,(string)$request->bearerToken()),403);
        $d=$request->validate(['phone'=>'required|regex:/^[1-9][0-9]{7,14}$/','group'=>'nullable|string|max:100',
            'mentioned'=>'required|boolean','id'=>'required|string|max:255','text'=>'present|nullable|string|max:10000','timestamp'=>'required|integer|min:1']);
        $employee=DB::table('employees')->where('phone',$d['phone'])->where('active',true)->first();
        $group=$d['group']??null;
        $command=preg_match('~^/(?:penting|cuti|izin|sakit|pdf)(?:\s|$)~i',trim($d['text']??''));
        if (!$employee || ($group && ((!$d['mentioned'] && !$command) || !DB::table('report_groups')->where('jid',$group)->exists()))) {
            return response()->noContent();
        }
        $text=trim($d['text']??'');
        if (preg_match('~^/penting\b~i',$text)) {
            return PinnedPostController::forChat($text);
        }
        if (preg_match('~^/pdf\b~i',$text)) {
            if ($group) return response()->json(['reply'=>'Kirim perintah /pdf lewat chat pribadi.']);
            if (!preg_match('~^/pdf(?:\s+(\d{4}-(?:0[1-9]|1[0-2])))?$~i',$text,$m)) {
                return response()->json(['reply'=>'Gunakan /pdf YYYY-MM, misalnya /pdf 2026-10.']);
            }
            $month=$m[1]??\Carbon\CarbonImmutable::createFromTimestamp($d['timestamp'],'Asia/Jakarta')->format('Y-m');
            if (!DB::table('daily_reports')->where('employee_id',$employee->id)->where('report_date','like',$month.'-%')->exists()) {
                return response()->json(['reply'=>'Belum ada laporan Anda untuk '.$month.'.']);
            }
            return MonthlyReport::download($employee,$month);
        }
        try {
            $data=ReportText::parse($text,$d['timestamp']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['reply'=>$e->getMessage()]);
        }
        DB::table('daily_reports')->insertOrIgnore($data+['employee_id'=>$employee->id,'author'=>$employee->name,
            'message_id'=>$d['id'],'created_at'=>now(),'updated_at'=>now()]);
        $row=DB::table('daily_reports')->where('message_id',$d['id'])->where('employee_id',$employee->id)->firstOrFail();
        return response()->json(['reply'=>'Tersimpan · '.($data['attendance']??'Masuk').' · '.$employee->name.' · '.\Carbon\Carbon::parse($row->report_date)->format('d/m/Y').' · Laporan #'.$row->id]);
    }
}