<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlyReportChat
{
    private const WEEKDAYS = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    public static function forEmployee(object $employee, string $command): string
    {
        if (!preg_match('~^/laporan\s+([\p{L}]+)\s+(\d{4})$~iu', $command, $match)) {
            return 'Gunakan /laporan BULAN TAHUN, misalnya /laporan November 2026.';
        }

        $month = array_search(mb_strtolower($match[1]), ReportText::MONTHS, true);
        $year = (int) $match[2];
        if ($month === false || $year < 1900 || $year > 2100) {
            return 'Bulan atau tahun tidak valid. Contoh: /laporan November 2026.';
        }

        $start = CarbonImmutable::create($year, $month + 1, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->endOfMonth();
        $reports = DB::table('daily_reports')
            ->where('employee_id', $employee->id)
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('report_date')->orderBy('id')->get()
            ->groupBy('report_date');

        $lines = ['*Laporan '.ucfirst(ReportText::MONTHS[$month]).' '.$year.'*', 'Pegawai: '.$employee->name, ''];
        $number = 0;
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $items = $reports->get($day->toDateString(), collect());
            if ($day->isWeekend() && $items->isEmpty()) continue;

            $lines[] = (++$number).'. '.self::WEEKDAYS[$day->isoWeekday()].', '.$day->day.' '.ucfirst(ReportText::MONTHS[$month]).' '.$year;
            if ($items->isEmpty()) {
                $lines[] = '  • Belum ada laporan.';
            } else {
                foreach ($items as $report) {
                    $lines[] = '  • Laporan';
                    $lines[] = '    - Kehadiran: '.($report->attendance ?: 'Masuk');
                    if (trim($report->body)) {
                        $lines[] = '    - Aktivitas:';
                        foreach (preg_split('/\R/u', trim($report->body)) as $activity) {
                            $lines[] = '      '.($activity === '' ? ' ' : $activity);
                        }
                    }
                    foreach (['progress'=>'Status', 'obstacle'=>'Kendala', 'initiative'=>'Jenis', 'note'=>'Keterangan'] as $field=>$label) {
                        if (trim($report->$field ?? '')) $lines[] = '    - '.$label.': '.$report->$field;
                    }
                }
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}
