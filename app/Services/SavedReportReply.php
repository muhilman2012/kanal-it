<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class SavedReportReply
{
    public static function format(object $report, string $employeeName): string
    {
        $date = CarbonImmutable::parse($report->report_date, 'Asia/Jakarta');
        $dateLabel = $date->day.' '.ucfirst(ReportText::MONTHS[$date->month - 1]).' '.$date->year;
        $attendance = $report->attendance ?: 'Masuk';

        $details = [];
        foreach (preg_split('/\R/u', trim($report->body ?? '')) as $line) {
            $line = trim(preg_replace('/^\s*(?:\d+[.)]|[-*•])\s+/u', '', trim($line)));
            if ($line !== '') $details[] = '- '.$line;
        }
        if (!$details) $details[] = '- Tidak ada uraian aktivitas.';

        $lines = [
            '*Tersimpan*',
            '',
            'Tanggal Laporan: '.$dateLabel,
            '',
            'Nama Pegawai: '.$employeeName,
            '',
            'Status Kehadiran: '.$attendance,
            '',
            'Detail Laporan:',
            implode("\n", $details),
        ];

        foreach (['progress'=>'Status Pekerjaan', 'obstacle'=>'Kendala', 'initiative'=>'Jenis', 'note'=>'Keterangan'] as $field=>$label) {
            if (trim($report->$field ?? '') !== '') $lines[] = "\n".$label.': '.$report->$field;
        }

        return implode("\n", $lines);
    }
}
