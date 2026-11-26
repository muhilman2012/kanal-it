<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ReportText
{
    public const MONTHS = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
    private const FIELDS = ['kehadiran'=>'attendance','status'=>'progress','kendala'=>'obstacle','jenis'=>'initiative','keterangan'=>'note'];

    public static function parse(string $text, int $timestamp): array
    {
        $date = CarbonImmutable::createFromTimestamp($timestamp, 'Asia/Jakarta')->toDateString();
        $monthNumbers = [];
        foreach (self::MONTHS as $index => $name) {
            $monthNumbers[$name] = $monthNumbers[substr($name,0,3)] = $index+1;
        }
        $monthNumbers['agt'] = 8;
        $months = implode('|', array_keys($monthNumbers));
        $dateExpression = '\b(?:(\d{4})-(\d{1,2})-(\d{1,2})|(\d{1,2})[/-](\d{1,2})[/-](\d{4})|(\d{1,2})\s+('.$months.')\s+(\d{4}))\b';
        $pattern = '~'.$dateExpression.'~iu';
        $absence = null;
        if (preg_match('~^/(cuti|izin|sakit)\b~iu',$text,$command)) {
            $absence = ucfirst(strtolower($command[1]));
            $text = trim(substr($text,strlen($command[0])));
            if (!preg_match('~^'.$dateExpression.'$~iu',$text)) {
                throw new InvalidArgumentException('Gunakan /'.strtolower($absence).' diikuti tanggal lengkap, misalnya 25 nov 2026.');
            }
        } elseif (str_starts_with($text,'/')) {
            throw new InvalidArgumentException('Kirim /halo untuk melihat menu atau /tutorial untuk panduan lengkap.');
        }
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER|PREG_UNMATCHED_AS_NULL);
        $dates = [];
        foreach ($matches as $m) {
            if ($m[1] !== null) {
                [$year,$month,$day] = array_slice($m,1,3);
            } elseif ($m[4] !== null) {
                [$day,$month,$year] = array_slice($m,4,3);
            } else {
                $day=$m[7]; $month=$monthNumbers[strtolower($m[8])]; $year=$m[9];
            }
            if (!checkdate((int)$month,(int)$day,(int)$year)) {
                throw new InvalidArgumentException('Tanggal tidak valid. Kirim ulang dengan tanggal, bulan, dan tahun yang benar.');
            }
            $date = sprintf('%04d-%02d-%02d',$year,$month,$day);
            $dates[$date] = true;
        }
        if (count($dates)>1) {
            throw new InvalidArgumentException('Ada beberapa tanggal. Kirim satu laporan terpisah untuk setiap tanggal.');
        }
        if ($absence !== null) {
            return ['report_date'=>$date,'attendance'=>$absence,'body'=>''];
        }
        // Only remove a leading date label. Dates inside activity descriptions remain meaningful.
        $text = preg_replace('~^\s*(?:tanggal\s*:\s*)?'.$dateExpression.'[ \t]*(?:[—–,:-][ \t]*)?~iu','',$text,1);
        $data = ['report_date'=>$date,'attendance'=>'Masuk'];
        $text = preg_replace('/[ \t]+(?=kendala[ \t]*:)/iu',"\n",$text);
        $lines = [];
        foreach (preg_split('/\R/u',$text) as $line) {
            if (preg_match('/^(kehadiran|status|kendala|jenis|keterangan)\s*:\s*(.*)$/iu',trim($line),$parts)) {
                $field=self::FIELDS[strtolower($parts[1])];
                if ($field !== 'attendance') $data[$field]=trim($parts[2]);
            } else {
                $lines[]=$line;
            }
        }
        $data['body']=trim(implode("\n",$lines));
        if (mb_strlen($data['body'])<3 || preg_match('/^(halo|hai|hi|tes|test|pagi|siang|sore|malam|lapor|laporan|ok)[.! ]*$/iu',$data['body'])) {
            throw new InvalidArgumentException('Kirim uraian pekerjaan, misalnya “Memperbaiki koneksi ruang rapat”.');
        }
        return $data;
    }
}
