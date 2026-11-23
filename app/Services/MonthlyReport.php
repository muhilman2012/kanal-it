<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class MonthlyReport
{
    public static function download(object $employee, string $month)
    {
        $start = CarbonImmutable::parse($month.'-01');
        $rows = DB::table('daily_reports')->where('employee_id',$employee->id)
            ->whereBetween('report_date',[$start->toDateString(),$start->endOfMonth()->toDateString()])
            ->orderBy('report_date')->orderBy('id')->get();
        abort_if($rows->isEmpty(),404,'Belum ada laporan untuk bulan ini.');
        $name = \Illuminate\Support\Str::title(\Illuminate\Support\Str::squish($employee->name));
        $safeName = preg_replace('/[^\pL\pM\pN .\'-]+/u','-', $name);
        $title = 'Laporan-'.$safeName.'-'.$start->locale('id')->isoFormat('MMMM-YYYY');
        $filename = $title.'.pdf';
        $fallback = preg_replace('/[^A-Za-z0-9 ._-]+/','-',\Illuminate\Support\Str::ascii($filename));
        $payload=['title'=>$title,'name'=>$name,'position'=>$employee->position,'month'=>$start->locale('id')->isoFormat('MMMM YYYY'),
            'signed'=>CarbonImmutable::parse($rows->last()->report_date)->locale('id')->isoFormat('D MMMM YYYY'),'rows'=>$rows];
        $process=new Process(['/usr/bin/python3',base_path('scripts/monthly_pdf.py')]);
        $process->setInput(json_encode($payload,JSON_THROW_ON_ERROR));
        $process->mustRun();
        return response($process->getOutput(),200,['Content-Type'=>'application/pdf',
            'Content-Disposition'=>\Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('attachment',$filename,$fallback),
            'X-Report-Filename'=>rawurlencode($filename),
            'Cache-Control'=>'private, no-store']);
    }
}